<?php

namespace Tests\Integration;

use App\Adapters\Outbound\Persistence\LaravelTransactionRunner;
use App\Adapters\Outbound\Persistence\PostgresCategoryRepository;
use App\Adapters\Outbound\Persistence\PostgresKitRepository;
use App\Adapters\Outbound\Persistence\PostgresLoanRepository;
use App\Adapters\Outbound\Persistence\PostgresParticipantRepository;
use App\Adapters\Outbound\Persistence\PostgresUserRepository;
use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\KitRepository;
use App\Application\Services\LendKitsService;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Kit;
use App\Domain\Participant;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\ValueObjects\DocumentNumber;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\FixedClock;
use Tests\TestCase;

/**
 * T-13 · RNF-2, ADR-002 y ADR-004 con DOS conexiones reales a PostgreSQL, como dos mentores en dos computadores.
 *
 * Aquí no sirve deshacer cada prueba con una transacción: la conexión B no vería lo que A escribió.
 * Los datos se confirman de verdad en robotics_test y tearDown() los borra.
 */
final class ConcurrencyTest extends TestCase
{
    use MigratesOnce;

    private const SENSORES = '22222222-2222-4222-8222-222222222222';

    private ConnectionInterface $a;

    private ConnectionInterface $b;

    private FixedClock $clock;

    private string $mentor;

    private string $ana;

    private string $carlos;

    private string $drone;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.pgsql_b' => config('database.connections.pgsql')]);
        $this->a = DB::connection();
        $this->b = DB::connection('pgsql_b');
        $this->clock = new FixedClock('2026-11-17T15:00:00Z');

        $users = new PostgresUserRepository($this->a);
        $participants = new PostgresParticipantRepository($this->a);
        $kits = new PostgresKitRepository($this->a);

        $this->mentor = $users->nextId();
        $users->add(User::create($this->mentor, 'mentor-concurrencia', 'hash', Role::Mentor));
        $this->ana = $participants->nextId();
        $participants->add(Participant::create($this->ana, 'Ana', new DocumentNumber('conc-111')));
        $this->carlos = $participants->nextId();
        $participants->add(Participant::create($this->carlos, 'Carlos', new DocumentNumber('conc-222')));
        $this->drone = $kits->nextId();
        $kits->add(Kit::create($this->drone, 'Dron Tello', 'DJI', new KitReference('CON-0001'), self::SENSORES, new Units(1)));
    }

    protected function tearDown(): void
    {
        // Borrar lo confirmado, en orden de dependencias
        $this->a->delete('DELETE FROM loan_item WHERE kit_id = ?', [$this->drone]);
        $this->a->delete('DELETE FROM loan WHERE loaned_by_user_id = ?', [$this->mentor]);
        $this->a->delete('DELETE FROM kit WHERE id = ?', [$this->drone]);
        $this->a->delete('DELETE FROM participant WHERE id IN (?, ?)', [$this->ana, $this->carlos]);
        $this->a->delete('DELETE FROM "user" WHERE id = ?', [$this->mentor]);
        DB::purge('pgsql_b');

        parent::tearDown();
    }

    private function lendService(ConnectionInterface $db, ?KitRepository $kits = null): LendKitsService
    {
        return new LendKitsService(
            new PostgresLoanRepository($db),
            $kits ?? new PostgresKitRepository($db),
            new PostgresParticipantRepository($db),
            new PostgresCategoryRepository($db),
            new LaravelTransactionRunner($db),
            $this->clock,
        );
    }

    public function test_two_mentors_lending_the_last_unit_one_wins_and_the_other_gets_kit_not_available(): void
    {
        // El mentor B abre el dron en su pantalla: lo lee con version = 1 y 1 unidad disponible
        $readByB = (new PostgresKitRepository($this->b))->findActive($this->drone);
        $this->assertSame([1, 1], [$readByB->version(), $readByB->availableUnits()]);

        // Mientras tanto, el mentor A presta el dron a Ana y confirma
        $this->lendService($this->a)->lend($this->ana, [$this->drone], $this->mentor);

        // B presta a Carlos con lo que leyó antes: para B todavía queda 1 unidad
        $staleKits = new StaleReadKitRepository(new PostgresKitRepository($this->b), $readByB);
        try {
            $this->lendService($this->b, $staleKits)->lend($this->carlos, [$this->drone], $this->mentor);
            $this->fail('B debió perder la carrera');
        } catch (KitNotAvailable $error) {
            $this->assertSame('KIT_NOT_AVAILABLE', $error->errorCode());
        }

        // Resultado en la base: un solo préstamo, 0 disponibles y la versión subió exactamente 1
        $kit = $this->a->selectOne('SELECT available_units, version FROM kit WHERE id = ?', [$this->drone]);
        $this->assertSame([0, 2], [(int) $kit->available_units, (int) $kit->version]);
        $this->assertSame(1, (int) $this->a->selectOne('SELECT count(*) AS n FROM loan_item WHERE kit_id = ?', [$this->drone])->n);
        $this->assertSame(0, (int) $this->a->selectOne(
            'SELECT count(*) AS n FROM loan WHERE participant_id = ?', [$this->carlos],
        )->n, 'el préstamo de Carlos se deshizo completo');
    }

    public function test_while_one_loan_holds_the_participant_another_loan_for_her_waits(): void
    {
        $participantsB = new PostgresParticipantRepository($this->b);

        $this->a->beginTransaction();
        try {
            // A empezó un préstamo para Ana y tiene su fila bloqueada (ADR-004)
            (new PostgresParticipantRepository($this->a))->lockForLoan($this->ana);

            // B intenta lo mismo: PostgreSQL lo hace esperar. Se le da un límite para no colgar la prueba
            $this->b->beginTransaction();
            $this->b->statement("SET LOCAL lock_timeout = '300ms'");
            try {
                $participantsB->lockForLoan($this->ana);
                $this->fail('B debió esperar el bloqueo de A');
            } catch (QueryException $error) {
                $this->assertSame('55P03', $error->getCode(), 'lock_not_available: B estaba esperando a A');
            } finally {
                $this->b->rollBack();
            }

            // Otro participante no espera: cada préstamo bloquea solo su fila
            $this->b->beginTransaction();
            $this->b->statement("SET LOCAL lock_timeout = '300ms'");
            $this->assertSame('Carlos', $participantsB->lockForLoan($this->carlos)->fullName());
            $this->b->rollBack();
        } finally {
            $this->a->rollBack();
        }
    }
}

/**
 * Repositorio que devuelve el kit tal como el mentor lo leyó antes, para reproducir
 * «leí con version = 1, pero otro guardó primero». Todo lo demás va a la base real.
 */
final class StaleReadKitRepository implements KitRepository
{
    public function __construct(
        private readonly PostgresKitRepository $real,
        private readonly Kit $staleCopy,
    ) {}

    public function nextId(): string
    {
        return $this->real->nextId();
    }

    public function findActive(string $id): ?Kit
    {
        return $id === $this->staleCopy->id() ? $this->staleCopy : $this->real->findActive($id);
    }

    public function findActiveByReference(KitReference $reference): ?Kit
    {
        return $this->real->findActiveByReference($reference);
    }

    public function search(?string $text, ?string $categoryId, PageRequest $page): Page
    {
        return $this->real->search($text, $categoryId, $page);
    }

    public function add(Kit $kit): void
    {
        $this->real->add($kit);
    }

    public function save(Kit $kit): void
    {
        $this->real->save($kit);
    }
}
