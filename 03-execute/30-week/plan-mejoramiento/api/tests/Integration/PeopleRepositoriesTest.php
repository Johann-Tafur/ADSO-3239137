<?php

namespace Tests\Integration;

use App\Adapters\Outbound\Persistence\PostgresCategoryRepository;
use App\Adapters\Outbound\Persistence\PostgresParticipantRepository;
use App\Adapters\Outbound\Persistence\PostgresUserRepository;
use App\Application\Paging\PageRequest;
use App\Domain\Category;
use App\Domain\Errors\DocumentTaken;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Participant;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\ValueObjects\DocumentNumber;

/** T-12: repositorios de participantes, usuarios y categorías contra PostgreSQL real. */
final class PeopleRepositoriesTest extends IntegrationTestCase
{
    private PostgresParticipantRepository $participants;

    private PostgresUserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->participants = new PostgresParticipantRepository($this->db());
        $this->users = new PostgresUserRepository($this->db());
    }

    private function newParticipant(string $name, string $document): Participant
    {
        $participant = Participant::create($this->participants->nextId(), $name, new DocumentNumber($document));
        $this->participants->add($participant);

        return $participant;
    }

    // ---------- Participantes (HU-05) ----------

    public function test_a_participant_is_saved_and_read_back(): void
    {
        $ana = $this->newParticipant('Ana Gómez', '1020304050');

        $this->assertSame('Ana Gómez', $this->participants->find($ana->id())->fullName());
        $this->assertSame($ana->id(), $this->participants->findByDocument(new DocumentNumber('1020304050'))->id());
        $this->assertNull($this->participants->find('no-es-un-uuid'));
    }

    public function test_a_repeated_document_is_stopped_by_the_unique_index(): void
    {
        $this->newParticipant('Ana Gómez', '1020304050');

        $this->expectException(DocumentTaken::class);
        $this->newParticipant('Otra persona', '1020304050');
    }

    public function test_search_by_name_or_document_in_human_order_with_total(): void
    {
        $this->newParticipant('Zoe Ruiz', '300');
        $this->newParticipant('álvaro Gómez', '1020');
        $this->newParticipant('Beatriz Gómez', '1021');

        $all = $this->participants->search(null, new PageRequest);
        $byName = $this->participants->search('GÓMEZ', new PageRequest);
        $byDocument = $this->participants->search('102', new PageRequest(page: 1, size: 1));

        $names = fn ($page) => array_map(fn (Participant $p) => $p->fullName(), $page->items);
        $this->assertSame(['álvaro Gómez', 'Beatriz Gómez', 'Zoe Ruiz'], $names($all));
        $this->assertSame(['álvaro Gómez', 'Beatriz Gómez'], $names($byName));
        $this->assertSame(['álvaro Gómez'], $names($byDocument));
        $this->assertSame(2, $byDocument->total);
    }

    public function test_lock_for_loan_reads_the_participant_with_for_update(): void
    {
        $ana = $this->newParticipant('Ana Gómez', '1020304050');

        $this->assertSame('Ana Gómez', $this->participants->lockForLoan($ana->id())->fullName());
        // Dentro de la transacción de la prueba, la fila queda bloqueada por esta conexión
        $locks = $this->db()->selectOne(
            "SELECT count(*) AS n FROM pg_locks l JOIN pg_class c ON c.oid = l.relation
              WHERE c.relname = 'participant' AND l.mode = 'RowShareLock' AND l.pid = pg_backend_pid()",
        );
        $this->assertSame(1, (int) $locks->n);
    }

    // ---------- Usuarios (HU-01, HU-02) ----------

    public function test_a_user_is_saved_and_found_by_username_with_its_role(): void
    {
        $this->users->add(User::create($this->users->nextId(), 'Laura ', 'hash-argon', Role::Mentor));

        $laura = $this->users->findByUsername('laura');
        $this->assertSame('laura', $laura->username());
        $this->assertSame(Role::Mentor, $laura->role());
        $this->assertSame('hash-argon', $laura->passwordHash());
        $this->assertNull($this->users->findByUsername('pedro'));
    }

    public function test_a_repeated_username_is_stopped_by_the_unique_index(): void
    {
        $this->users->add(User::create($this->users->nextId(), 'laura', 'hash-1', Role::Mentor));

        $this->expectException(UsernameTaken::class);
        $this->users->add(User::create($this->users->nextId(), 'laura', 'hash-2', Role::Mentor));
    }

    // ---------- Categorías ----------

    public function test_the_five_seeded_categories_come_in_human_order(): void
    {
        $categories = new PostgresCategoryRepository($this->db());

        $this->assertSame(
            ['Competencia', 'Drones', 'Impresión 3D', 'Iniciación', 'Sensores'],
            array_map(fn (Category $c) => $c->name, $categories->all()),
        );
        $this->assertSame('Drones', $categories->find('33333333-3333-4333-8333-333333333333')->name);
        $this->assertNull($categories->find('no-es-un-uuid'));
    }
}
