<?php

namespace Tests\Unit\Application;

use App\Application\Results\ReportRow;
use App\Application\Services\CreateKitService;
use App\Application\Services\CreateParticipantService;
use App\Application\Services\LendKitsService;
use App\Application\Services\MostRequestedReportService;
use App\Application\Services\ReturnItemService;
use App\Application\Services\UpdateKitService;
use App\Domain\Errors\InvalidDateRange;
use App\Domain\Loan;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransactionRunner;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryCategoryRepository as Categories;
use Tests\Support\InMemoryKitRepository;
use Tests\Support\InMemoryLoanRepository;
use Tests\Support\InMemoryParticipantRepository;
use Tests\Support\InMemoryReportReader;

/** T-11 · HU-10: reporte de kits más solicitados (CA-10.1 a CA-10.4), contra fakes. */
final class MostRequestedReportServiceTest extends TestCase
{
    private FixedClock $clock;

    private InMemoryKitRepository $kits;

    private Categories $categories;

    private LendKitsService $lend;

    private ReturnItemService $return;

    private MostRequestedReportService $service;

    /** @var array<string, string> referencia → id */
    private array $kit = [];

    /** @var list<string> */
    private array $people = [];

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-02T15:00:00Z');
        $this->categories = new Categories;
        $participants = new InMemoryParticipantRepository;
        $this->kits = new InMemoryKitRepository($this->categories);
        $loans = new InMemoryLoanRepository($participants);
        $transaction = new FakeTransactionRunner($this->kits, $loans);
        $this->lend = new LendKitsService($loans, $this->kits, $participants, $this->categories, $transaction, $this->clock);
        $this->return = new ReturnItemService($loans, $this->kits, $transaction, $this->clock);
        $this->service = new MostRequestedReportService(new InMemoryReportReader($loans));

        $create = new CreateKitService($this->kits, $this->categories);
        $this->kit['ARD-0001'] = $create->create('Arduino Starter Kit', 'Arduino', 'ARD-0001', Categories::INICIACION, 10)->kit->id();
        $this->kit['DRN-0001'] = $create->create('Dron Tello', 'DJI', 'DRN-0001', Categories::DRONES, 10)->kit->id();
        $this->kit['SEN-0001'] = $create->create('Sensor ultrasónico', 'Elegoo', 'SEN-0001', Categories::SENSORES, 10)->kit->id();

        $createParticipant = new CreateParticipantService($participants);
        for ($i = 1; $i <= 6; $i++) {
            $this->people[] = $createParticipant->create("Participante {$i}", "doc-{$i}")->id();
        }
    }

    /** Presta y devuelve en el acto, para que el participante pueda volver a pedir. */
    private function lendAndReturn(int $person, string ...$references): void
    {
        $kitIds = array_map(fn (string $reference) => $this->kit[$reference], $references);
        $loan = $this->lend->lend($this->people[$person], $kitIds, 'mentor-1')->loan;
        $this->returnAll($loan);
    }

    private function returnAll(Loan $loan): void
    {
        foreach ($loan->items() as $item) {
            $this->return->returnItem($loan->id(), $item->id);
        }
    }

    private static function november(): DateRange
    {
        return new DateRange(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-30'));
    }

    /** @return list<array{string, string, int}> */
    private function rows(DateRange $range): array
    {
        return array_map(
            fn (ReportRow $row) => [$row->kitName, $row->categoryName, $row->timesRequested],
            $this->service->report($range)->rows,
        );
    }

    public function test_one_row_per_kit_from_most_to_least_requested(): void
    {
        $this->lendAndReturn(0, 'ARD-0001', 'DRN-0001');
        $this->lendAndReturn(1, 'ARD-0001');
        $this->lendAndReturn(2, 'ARD-0001', 'SEN-0001');
        $this->lendAndReturn(3, 'DRN-0001');

        $this->assertSame([
            ['Arduino Starter Kit', 'Iniciación', 3],
            ['Dron Tello', 'Drones', 2],
            ['Sensor ultrasónico', 'Sensores', 1],
        ], $this->rows(self::november()));
    }

    public function test_ties_are_ordered_by_kit_name(): void
    {
        $this->lendAndReturn(0, 'SEN-0001');
        $this->lendAndReturn(1, 'DRN-0001');
        $this->lendAndReturn(2, 'ARD-0001');

        $names = array_column($this->rows(self::november()), 0);
        $this->assertSame(['Arduino Starter Kit', 'Dron Tello', 'Sensor ultrasónico'], $names);
    }

    public function test_only_loans_inside_the_range_count_and_both_days_are_complete(): void
    {
        $this->clock->set('2026-10-31T23:59:59Z');
        $this->lendAndReturn(0, 'ARD-0001'); // fuera: un segundo antes del 1-nov
        $this->clock->set('2026-11-30T23:59:59Z');
        $this->lendAndReturn(1, 'DRN-0001'); // dentro: último segundo del 30-nov
        $this->clock->set('2026-12-01T00:00:00Z');
        $this->lendAndReturn(2, 'SEN-0001'); // fuera: primer instante de diciembre

        $this->assertSame([['Dron Tello', 'Drones', 1]], $this->rows(self::november()));
    }

    public function test_ties_follow_spanish_alphabetical_order_not_bytes(): void
    {
        // strcmp() daría «Zumo», «arduino», «Órbita»: mayúsculas antes que minúsculas y acentos al final
        $rows = [
            new ReportRow('k1', 'Zumo', 'Competencia', 1),
            new ReportRow('k2', 'Órbita', 'Drones', 1),
            new ReportRow('k3', 'arduino', 'Iniciación', 1),
            new ReportRow('k4', 'Muy pedido', 'Sensores', 5),
        ];
        $reader = new class($rows) implements \App\Application\Ports\ReportReader
        {
            public function __construct(private array $rows) {}

            public function mostRequested(DateRange $range): array
            {
                return $this->rows;
            }
        };

        $names = array_map(fn (ReportRow $row) => $row->kitName, (new MostRequestedReportService($reader))->report(self::november())->rows);
        $this->assertSame(['Muy pedido', 'arduino', 'Órbita', 'Zumo'], $names);
    }

    public function test_a_range_without_loans_is_an_empty_list_not_an_error(): void
    {
        $this->lendAndReturn(0, 'ARD-0001');

        $empty = new DateRange(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2025-01-31'));
        $this->assertSame([], $this->service->report($empty)->rows);
    }

    public function test_an_inverted_range_is_invalid_date_range(): void
    {
        $this->expectException(InvalidDateRange::class);
        new DateRange(new DateTimeImmutable('2026-11-30'), new DateTimeImmutable('2026-11-01'));
    }

    public function test_renaming_the_kit_later_does_not_change_a_closed_period(): void
    {
        $this->lendAndReturn(0, 'ARD-0001');
        $this->lendAndReturn(1, 'ARD-0001');
        $before = $this->rows(self::november());

        // En diciembre el admin renombra el kit y lo pasa a otra categoría
        $this->clock->set('2026-12-05T10:00:00Z');
        (new UpdateKitService($this->kits, $this->categories))
            ->update($this->kit['ARD-0001'], 'Arduino Uno R4', 'Arduino', 'ARD-0001', Categories::SENSORES, 10);

        $this->assertSame([['Arduino Starter Kit', 'Iniciación', 2]], $before);
        $this->assertSame($before, $this->rows(self::november()), 'noviembre no cambia (CA-10.4, artículo VIII)');
    }

    public function test_after_a_rename_the_new_name_is_a_separate_row(): void
    {
        $this->lendAndReturn(0, 'ARD-0001');
        (new UpdateKitService($this->kits, $this->categories))
            ->update($this->kit['ARD-0001'], 'Arduino Uno R4', 'Arduino', 'ARD-0001', Categories::INICIACION, 10);
        $this->lendAndReturn(1, 'ARD-0001');

        $this->assertSame([
            ['Arduino Starter Kit', 'Iniciación', 1],
            ['Arduino Uno R4', 'Iniciación', 1],
        ], $this->rows(self::november()));
    }

    public function test_the_report_carries_no_participant_or_mentor(): void
    {
        $fields = array_map(fn ($property) => $property->getName(), (new \ReflectionClass(ReportRow::class))->getProperties());

        $this->assertSame(['kitId', 'kitName', 'categoryName', 'timesRequested'], $fields);
    }
}
