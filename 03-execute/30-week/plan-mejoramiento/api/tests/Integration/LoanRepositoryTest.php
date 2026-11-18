<?php

namespace Tests\Integration;

use App\Adapters\Outbound\Persistence\LaravelTransactionRunner;
use App\Adapters\Outbound\Persistence\PostgresCategoryRepository;
use App\Adapters\Outbound\Persistence\PostgresKitRepository;
use App\Adapters\Outbound\Persistence\PostgresLoanRepository;
use App\Adapters\Outbound\Persistence\PostgresParticipantRepository;
use App\Adapters\Outbound\Persistence\PostgresReportReader;
use App\Adapters\Outbound\Persistence\PostgresUserRepository;
use App\Application\Paging\PageRequest;
use App\Application\Results\LoanFilter;
use App\Application\Services\LendKitsService;
use App\Application\Services\ListLoansService;
use App\Application\Services\MostRequestedReportService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Kit;
use App\Domain\LoanStatus;
use App\Domain\Participant;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\ValueObjects\DateRange;
use App\Domain\ValueObjects\DocumentNumber;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use DateTimeImmutable;
use Tests\Support\FixedClock;

/**
 * T-13: PostgresLoanRepository, PostgresReportReader y LaravelTransactionRunner contra PostgreSQL real,
 * usados por los casos de uso de verdad (no por fakes).
 */
final class LoanRepositoryTest extends IntegrationTestCase
{
    private const SENSORES = '22222222-2222-4222-8222-222222222222';

    private FixedClock $clock;

    private PostgresKitRepository $kits;

    private PostgresLoanRepository $loans;

    private LendKitsService $lend;

    private ReturnItemService $return;

    private string $mentor;

    private string $ana;

    private string $carlos;

    /** @var array<string, string> referencia → id */
    private array $kit = [];

    protected function setUp(): void
    {
        parent::setUp();
        $db = $this->db();
        $this->clock = new FixedClock('2026-11-01T15:00:00Z');
        $this->kits = new PostgresKitRepository($db);
        $this->loans = new PostgresLoanRepository($db);
        $participants = new PostgresParticipantRepository($db);
        $users = new PostgresUserRepository($db);
        $transaction = new LaravelTransactionRunner($db);
        $this->lend = new LendKitsService($this->loans, $this->kits, $participants, new PostgresCategoryRepository($db), $transaction, $this->clock);
        $this->return = new ReturnItemService($this->loans, $this->kits, $transaction, $this->clock);

        $this->mentor = $users->nextId();
        $users->add(User::create($this->mentor, 'laura', 'hash', Role::Mentor));
        foreach (['ana' => '111', 'carlos' => '222'] as $name => $document) {
            $id = $participants->nextId();
            $participants->add(Participant::create($id, ucfirst($name), new DocumentNumber($document)));
            $this->{$name} = $id;
        }
        foreach ([['ARD-0001', 'Arduino', 3], ['DRN-0001', 'Dron', 1], ['SEN-0001', 'Sensor', 2]] as [$reference, $name, $units]) {
            $id = $this->kits->nextId();
            $this->kits->add(Kit::create($id, $name, 'Fabricante', new KitReference($reference), self::SENSORES, new Units($units)));
            $this->kit[$reference] = $id;
        }
    }

    private function available(string $reference): int
    {
        return $this->kits->findActive($this->kit[$reference])->availableUnits();
    }

    private function rowsIn(string $table): int
    {
        return (int) $this->db()->selectOne("SELECT count(*) AS n FROM {$table}")->n;
    }

    // ---------- Guardar y leer ----------

    public function test_a_loan_is_stored_with_its_lines_and_read_back_identical(): void
    {
        $loan = $this->lend->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['DRN-0001']], $this->mentor)->loan;

        $read = $this->loans->find($loan->id());

        $this->assertSame($this->ana, $read->participantId());
        $this->assertSame($this->mentor, $read->loanedByUserId());
        $this->assertEquals(new DateTimeImmutable('2026-11-01T15:00:00Z'), $read->loanedAt());
        $this->assertSame(['Arduino', 'Dron'], array_map(fn ($item) => $item->kitName, $read->items()));
        $this->assertEquals(new DateTimeImmutable('2026-11-13T15:00:00Z'), $read->items()[0]->dueAt);
        $this->assertSame('Sensores', $read->items()[0]->categoryName);
        $this->assertSame(2, $this->available('ARD-0001'));
        $this->assertSame(2, $this->kits->findActive($this->kit['ARD-0001'])->version(), 'la versión subió en 1');
    }

    public function test_an_unknown_or_non_uuid_loan_is_null(): void
    {
        $this->assertNull($this->loans->find($this->loans->nextId()));
        $this->assertNull($this->loans->find('hola'));
    }

    // ---------- La transacción real (CA-06.3) ----------

    public function test_if_the_second_kit_has_no_units_postgres_keeps_nothing(): void
    {
        $this->lend->lend($this->carlos, [$this->kit['DRN-0001']], $this->mentor); // se lleva el único dron

        try {
            $this->lend->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['DRN-0001']], $this->mentor);
            $this->fail('Debió rechazar el dron');
        } catch (KitNotAvailable) {
        }
        $this->assertSame(3, $this->available('ARD-0001'));
        $this->assertSame(1, $this->rowsIn('loan'));
        $this->assertSame(1, $this->rowsIn('loan_item'));
    }

    // ---------- Devolver (HU-07) ----------

    public function test_returning_sets_returned_at_and_gives_the_unit_back_once(): void
    {
        $loan = $this->lend->lend($this->ana, [$this->kit['ARD-0001']], $this->mentor)->loan;
        $this->clock->advance('+2 days');

        $this->return->returnItem($loan->id(), $loan->items()[0]->id);

        $this->assertEquals(new DateTimeImmutable('2026-11-03T15:00:00Z'), $this->loans->find($loan->id())->items()[0]->returnedAt);
        $this->assertSame(3, $this->available('ARD-0001'));
        $this->assertSame([], $this->loans->openItemsOf($this->ana));
    }

    public function test_mark_returned_on_an_already_returned_line_affects_zero_rows(): void
    {
        $loan = $this->lend->lend($this->ana, [$this->kit['ARD-0001']], $this->mentor)->loan;
        $returned = $loan->items()[0]->withReturnedAt($this->clock->now());
        $this->loans->markReturned($returned);

        $this->expectException(ItemAlreadyReturned::class);
        $this->loans->markReturned($returned);
    }

    // ---------- Consultas (HU-08, HU-09) ----------

    /** Ana el 1-nov (vence 13), Carlos el 5-nov y devuelve; hoy 16-nov. */
    private function history(): array
    {
        $ana = $this->lend->lend($this->ana, [$this->kit['ARD-0001']], $this->mentor)->loan;
        $this->clock->set('2026-11-05T09:00:00Z');
        $carlos = $this->lend->lend($this->carlos, [$this->kit['SEN-0001']], $this->mentor)->loan;
        $this->return->returnItem($carlos->id(), $carlos->items()[0]->id);
        $this->clock->set('2026-11-16T15:00:00Z');

        return [$ana->id(), $carlos->id()];
    }

    public function test_status_is_computed_in_sql_with_now(): void
    {
        [$ana, $carlos] = $this->history();
        $now = $this->clock->now();
        $ids = fn (?LoanStatus $status) => array_map(
            fn ($loan) => $loan->id(),
            $this->loans->search(new LoanFilter(status: $status), new PageRequest, $now)->items,
        );

        $this->assertSame([$carlos, $ana], $ids(null), 'el más reciente primero');
        $this->assertSame([$ana], $ids(LoanStatus::Overdue));
        $this->assertSame([$carlos], $ids(LoanStatus::Closed));
        $this->assertSame([], $ids(LoanStatus::Open));
        $this->assertSame([$ana], array_map(fn ($l) => $l->id(), $this->loans->search(new LoanFilter(status: LoanStatus::Open), new PageRequest, new DateTimeImmutable('2026-11-10T00:00:00Z'))->items), 'el 10-nov Ana aún no estaba vencida');
    }

    public function test_list_filters_by_range_and_participant(): void
    {
        [$ana, $carlos] = $this->history();
        $list = new ListLoansService($this->loans, $this->clock);

        $byRange = $list->list(DateRange::fromDates('2026-11-02', '2026-11-30'), null, null, new PageRequest);
        $byParticipant = $list->list(null, null, $this->ana, new PageRequest);

        $this->assertSame([$carlos], array_map(fn ($view) => $view->loan->id(), $byRange->items));
        $this->assertSame([$ana], array_map(fn ($view) => $view->loan->id(), $byParticipant->items));
        $this->assertSame(LoanStatus::Overdue, $byParticipant->items[0]->status);
    }

    public function test_overdue_lines_bring_the_participant_name_and_days_late(): void
    {
        $this->history();

        $lines = (new OverdueService($this->loans, $this->clock))->list();

        $this->assertCount(1, $lines);
        $this->assertSame(['Ana', 'Arduino', 3], [$lines[0]->participantName, $lines[0]->item->kitName, $lines[0]->daysLate]);
    }

    // ---------- Reporte (HU-10, artículo VIII) ----------

    public function test_the_report_groups_frozen_names_with_one_query_and_ignores_later_renames(): void
    {
        $this->history();
        $this->clock->set('2026-11-20T10:00:00Z');
        $this->lend->lend($this->carlos, [$this->kit['ARD-0001']], $this->mentor);

        // Después se renombra el Arduino en el catálogo
        $arduino = $this->kits->findActive($this->kit['ARD-0001']);
        $arduino->updateDetails('Arduino Uno R4', 'Arduino', new KitReference('ARD-0001'), self::SENSORES);
        $this->kits->save($arduino);

        $report = (new MostRequestedReportService(new PostgresReportReader($this->db())))->report(DateRange::fromDates('2026-11-01', '2026-11-30'));

        $this->assertSame(
            [['Arduino', 'Sensores', 2], ['Sensor', 'Sensores', 1]],
            array_map(fn ($row) => [$row->kitName, $row->categoryName, $row->timesRequested], $report->rows),
        );
    }
}
