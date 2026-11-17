<?php

namespace Tests\Unit\Application;

use App\Application\Paging\PageRequest;
use App\Application\Results\LoanView;
use App\Application\Results\OverdueLine;
use App\Application\Services\CreateKitService;
use App\Application\Services\CreateParticipantService;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendKitsService;
use App\Application\Services\ListLoansService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use App\Domain\Errors\InvalidDateRange;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use App\Domain\LoanStatus;
use App\Domain\ValueObjects\DateRange;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransactionRunner;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryCategoryRepository as Categories;
use Tests\Support\InMemoryKitRepository;
use Tests\Support\InMemoryLoanRepository;
use Tests\Support\InMemoryParticipantRepository;

/**
 * T-10 · HU-08 y HU-09: ver un préstamo, listar préstamos y kits vencidos, contra fakes.
 *
 * Escenario (todo en noviembre de 2026, a las 15:00 UTC):
 *   día 01  Ana    presta ARD-0001            → vence el 13
 *   día 03  Carlos presta SEN-0001            → vence el 15
 *   día 10  Diana  presta DRN-0001 y la devuelve el mismo día
 *   hoy = día 16: Ana lleva 3 días de retraso, Carlos 1, Diana está cerrado.
 */
final class LoanQueriesTest extends TestCase
{
    private FixedClock $clock;

    private InMemoryLoanRepository $loans;

    private GetLoanService $get;

    private ListLoansService $list;

    private OverdueService $overdue;

    /** @var array<string, string> nombre → id de su préstamo */
    private array $loanOf = [];

    /** @var array<string, string> nombre → id del participante */
    private array $participant = [];

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-01T15:00:00Z');
        $categories = new Categories;
        $participants = new InMemoryParticipantRepository;
        $kits = new InMemoryKitRepository($categories);
        $this->loans = new InMemoryLoanRepository($participants);
        $transaction = new FakeTransactionRunner($kits, $this->loans);
        $lend = new LendKitsService($this->loans, $kits, $participants, $categories, $transaction, $this->clock);
        $return = new ReturnItemService($this->loans, $kits, $transaction, $this->clock);

        $createKit = new CreateKitService($kits, $categories);
        $createParticipant = new CreateParticipantService($participants);
        $kit = [];
        foreach (['ARD-0001', 'SEN-0001', 'DRN-0001'] as $reference) {
            $kit[$reference] = $createKit->create("Kit {$reference}", 'Fabricante', $reference, Categories::SENSORES, 2)->kit->id();
        }
        foreach (['Ana' => '111', 'Carlos' => '222', 'Diana' => '333'] as $name => $document) {
            $this->participant[$name] = $createParticipant->create($name, $document)->id();
        }

        $this->loanOf['Ana'] = $lend->lend($this->participant['Ana'], [$kit['ARD-0001']], 'mentor-1')->loan->id();
        $this->clock->advance('+2 days');
        $this->loanOf['Carlos'] = $lend->lend($this->participant['Carlos'], [$kit['SEN-0001']], 'mentor-1')->loan->id();
        $this->clock->advance('+7 days');
        $diana = $lend->lend($this->participant['Diana'], [$kit['DRN-0001']], 'mentor-1')->loan;
        $return->returnItem($diana->id(), $diana->items()[0]->id);
        $this->loanOf['Diana'] = $diana->id();
        $this->clock->advance('+6 days'); // hoy: 16 de noviembre

        $this->get = new GetLoanService($this->loans, $this->clock);
        $this->list = new ListLoansService($this->loans, $this->clock);
        $this->overdue = new OverdueService($this->loans, $this->clock);
    }

    /** @param list<LoanView> $views @return list<string> */
    private function owners(array $views): array
    {
        return array_map(fn (LoanView $view) => array_search($view->loan->id(), $this->loanOf, true), $views);
    }

    private static function range(string $from, string $to): DateRange
    {
        return new DateRange(new DateTimeImmutable($from), new DateTimeImmutable($to));
    }

    // ---------- Ver uno (CA-08.1) ----------

    public function test_a_loan_comes_with_its_lines_and_computed_status(): void
    {
        $ana = $this->get->get($this->loanOf['Ana']);
        $diana = $this->get->get($this->loanOf['Diana']);

        $this->assertSame(LoanStatus::Overdue, $ana->status);
        $this->assertSame('ARD-0001', $ana->loan->items()[0]->kitReference);
        $this->assertSame(LoanStatus::Closed, $diana->status);
    }

    public function test_an_unknown_loan_is_not_found(): void
    {
        $this->expectException(NotFound::class);
        $this->get->get('no-existe');
    }

    // ---------- Listar (CA-08.2) ----------

    public function test_without_filters_the_newest_comes_first(): void
    {
        $page = $this->list->list(null, null, null, new PageRequest);

        $this->assertSame(['Diana', 'Carlos', 'Ana'], $this->owners($page->items));
        $this->assertSame(3, $page->total);
    }

    public function test_filters_by_computed_status(): void
    {
        $this->assertSame(['Carlos', 'Ana'], $this->owners($this->list->list(null, 'overdue', null, new PageRequest)->items));
        $this->assertSame(['Diana'], $this->owners($this->list->list(null, 'closed', null, new PageRequest)->items));
        $this->assertSame([], $this->owners($this->list->list(null, 'open', null, new PageRequest)->items));
    }

    public function test_filters_by_participant(): void
    {
        $page = $this->list->list(null, null, $this->participant['Carlos'], new PageRequest);

        $this->assertSame(['Carlos'], $this->owners($page->items));
    }

    public function test_the_range_includes_both_days_completely(): void
    {
        // Del 1 al 3: entran Ana (día 1, 15:00) y Carlos (día 3, 15:00); Diana (día 10) no
        $page = $this->list->list(self::range('2026-11-01', '2026-11-03'), null, null, new PageRequest);

        $this->assertSame(['Carlos', 'Ana'], $this->owners($page->items));
    }

    public function test_an_inverted_range_is_invalid_date_range(): void
    {
        $this->expectException(InvalidDateRange::class);
        self::range('2026-11-10', '2026-11-01');
    }

    public function test_an_unknown_status_is_a_validation_error(): void
    {
        $this->expectException(ValidationError::class);
        $this->list->list(null, 'perdido', null, new PageRequest);
    }

    public function test_pages_keep_the_total(): void
    {
        $page = $this->list->list(null, null, null, new PageRequest(page: 2, size: 2));

        $this->assertSame(['Ana'], $this->owners($page->items));
        $this->assertSame(3, $page->total);
    }

    // ---------- Vencidos (HU-09) ----------

    public function test_overdue_lines_come_with_participant_and_days_late_most_late_first(): void
    {
        $lines = $this->overdue->list();

        $this->assertSame(
            [['Ana', 'ARD-0001', 3], ['Carlos', 'SEN-0001', 1]],
            array_map(fn (OverdueLine $line) => [$line->participantName, $line->item->kitReference, $line->daysLate], $lines),
        );
    }

    public function test_a_returned_line_is_never_overdue(): void
    {
        $this->clock->advance('+30 days');

        $names = array_map(fn (OverdueLine $line) => $line->participantName, $this->overdue->list());
        $this->assertNotContains('Diana', $names);
    }
}
