<?php

namespace Tests\Unit\Application;

use App\Application\Services\CreateKitService;
use App\Application\Services\CreateParticipantService;
use App\Application\Services\LendKitsService;
use App\Application\Services\SearchParticipantsService;
use App\Domain\Errors\DuplicateKitInLoan;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ParticipantHasOverdue;
use App\Domain\Errors\ParticipantLimitExceeded;
use App\Domain\Errors\ValidationError;
use App\Domain\LoanStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransactionRunner;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryCategoryRepository as Categories;
use Tests\Support\InMemoryKitRepository;
use Tests\Support\InMemoryLoanRepository;
use Tests\Support\InMemoryParticipantRepository;

/** T-09 · HU-06 completa: registrar un préstamo, contra fakes. */
final class LendKitsServiceTest extends TestCase
{
    private FixedClock $clock;

    private InMemoryKitRepository $kits;

    private InMemoryLoanRepository $loans;

    private InMemoryParticipantRepository $participants;

    private FakeTransactionRunner $transaction;

    private LendKitsService $service;

    private string $ana;

    /** @var array<string, string> referencia → id */
    private array $kit = [];

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-16T15:00:00Z');
        $categories = new Categories;
        $this->kits = new InMemoryKitRepository($categories);
        $this->loans = new InMemoryLoanRepository;
        $this->participants = new InMemoryParticipantRepository;
        $this->transaction = new FakeTransactionRunner($this->kits, $this->loans);
        $this->service = new LendKitsService($this->loans, $this->kits, $this->participants, $categories, $this->transaction, $this->clock);

        $this->ana = (new CreateParticipantService($this->participants))->create('Ana Gómez', '1020304050')->id();

        $create = new CreateKitService($this->kits, $categories);
        foreach ([['ARD-0001', 3], ['ARD-0002', 3], ['SEN-0001', 3], ['DRN-0001', 1]] as [$reference, $units]) {
            $this->kit[$reference] = $create->create("Kit {$reference}", 'Fabricante', $reference, Categories::SENSORES, $units)->kit->id();
        }
    }

    private function available(string $reference): int
    {
        return $this->kits->stored($this->kit[$reference])->availableUnits();
    }

    // ---------- El camino feliz ----------

    public function test_lends_two_kits_takes_one_unit_of_each_and_saves_the_loan(): void
    {
        $view = $this->service->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['SEN-0001']], 'mentor-1');

        $this->assertSame(LoanStatus::Open, $view->status);
        $this->assertSame('mentor-1', $view->loan->loanedByUserId());
        $this->assertCount(2, $view->loan->items());
        $this->assertSame(2, $this->available('ARD-0001'));
        $this->assertSame(2, $this->available('SEN-0001'));
        $this->assertSame(1, $this->loans->count());
        $this->assertSame(1, $this->transaction->runs);
    }

    public function test_each_line_is_due_in_12_days_and_freezes_the_category_name(): void
    {
        $item = $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1')->loan->items()[0];

        $this->assertEquals(new DateTimeImmutable('2026-11-28T15:00:00Z'), $item->dueAt);
        $this->assertSame('Sensores', $item->categoryName);
        $this->assertSame('ARD-0001', $item->kitReference);
    }

    public function test_the_kit_version_goes_up_by_one(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1');

        $this->assertSame(2, $this->kits->stored($this->kit['ARD-0001'])->version());
    }

    // ---------- Entrada (CA-06.1, CA-06.2) ----------

    public function test_zero_or_three_kits_are_rejected_before_touching_anything(): void
    {
        foreach ([[], [$this->kit['ARD-0001'], $this->kit['ARD-0002'], $this->kit['SEN-0001']]] as $kitIds) {
            try {
                $this->service->lend($this->ana, $kitIds, 'mentor-1');
                $this->fail('Debió rechazar la cantidad');
            } catch (ValidationError $error) {
                $this->assertSame('items', $error->details()[0]['field']);
            }
        }
        $this->assertSame(0, $this->transaction->runs, 'ni siquiera abre la transacción');
    }

    public function test_the_same_kit_twice_is_rejected(): void
    {
        $this->expectException(DuplicateKitInLoan::class);
        $this->service->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['ARD-0001']], 'mentor-1');
    }

    // ---------- Límite de 2 sin devolver (CA-06.4, RN-2) ----------

    public function test_a_third_kit_without_returning_is_limit_exceeded_with_the_current_count(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['ARD-0002']], 'mentor-1');

        try {
            $this->service->lend($this->ana, [$this->kit['SEN-0001']], 'mentor-1');
            $this->fail('Debió rechazar el tercer kit');
        } catch (ParticipantLimitExceeded $error) {
            $this->assertSame('PARTICIPANT_LIMIT_EXCEEDED', $error->errorCode());
            $this->assertSame([['field' => 'items', 'issue' => 'sin devolver: 2']], $error->details());
        }
        $this->assertSame(3, $this->available('SEN-0001'));
    }

    public function test_one_open_plus_two_new_is_also_over_the_limit(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1');

        $this->expectException(ParticipantLimitExceeded::class);
        $this->service->lend($this->ana, [$this->kit['ARD-0002'], $this->kit['SEN-0001']], 'mentor-1');
    }

    public function test_one_open_plus_one_new_is_allowed_and_counts_in_active_items(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1');
        $this->service->lend($this->ana, [$this->kit['ARD-0002']], 'mentor-1');

        $detail = (new SearchParticipantsService($this->participants, $this->loans))->get($this->ana);
        $this->assertSame(2, $detail->activeItems);
    }

    // ---------- Vencidos (CA-06.5, RN-3) ----------

    public function test_a_participant_with_an_overdue_kit_cannot_borrow(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1');
        $this->clock->advance('+13 days');

        try {
            $this->service->lend($this->ana, [$this->kit['ARD-0002']], 'mentor-1');
            $this->fail('Debió rechazar al participante con vencidos');
        } catch (ParticipantHasOverdue $error) {
            $this->assertSame('PARTICIPANT_HAS_OVERDUE', $error->errorCode());
        }
        $this->assertSame(3, $this->available('ARD-0002'));
    }

    public function test_on_the_due_instant_it_is_not_overdue_yet(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1');
        $this->clock->advance('+12 days');

        $view = $this->service->lend($this->ana, [$this->kit['ARD-0002']], 'mentor-1');

        $this->assertSame(LoanStatus::Open, $view->status);
    }

    // ---------- Unidades (CA-06.3) ----------

    public function test_a_kit_without_units_is_rejected_and_nothing_is_registered(): void
    {
        $other = (new CreateParticipantService($this->participants))->create('Carlos Ruiz', '9988776655')->id();
        $this->service->lend($other, [$this->kit['DRN-0001']], 'mentor-1'); // se lleva el único dron

        try {
            $this->service->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['DRN-0001']], 'mentor-1');
            $this->fail('Debió rechazar el dron sin unidades');
        } catch (KitNotAvailable $error) {
            $this->assertSame('KIT_NOT_AVAILABLE', $error->errorCode());
        }
        $this->assertSame(3, $this->available('ARD-0001'), 'el Arduino no se descuenta');
        $this->assertSame(1, $this->loans->count(), 'solo existe el préstamo de Carlos');
    }

    public function test_losing_the_race_on_the_second_kit_rolls_back_the_first(): void
    {
        // Entre la lectura y el guardado, otro mentor cambió el dron (RNF-2, ADR-002)
        $this->kits->failNextSaveOf($this->kit['DRN-0001']);

        try {
            $this->service->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['DRN-0001']], 'mentor-1');
            $this->fail('Debió perder la carrera');
        } catch (KitNotAvailable) {
        }
        $this->assertSame(1, $this->transaction->rollbacks);
        $this->assertSame(3, $this->available('ARD-0001'), 'la transacción deshizo el Arduino ya guardado');
        $this->assertSame(1, $this->kits->stored($this->kit['ARD-0001'])->version());
        $this->assertSame(0, $this->loans->count());
    }

    // ---------- Concurrencia (ADR-004) ----------

    public function test_the_participant_row_is_locked_inside_the_transaction_before_counting(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001']], 'mentor-1');

        $this->assertSame([$this->ana], $this->participants->locked);
    }

    public function test_a_rejected_loan_still_locked_the_participant_first(): void
    {
        $this->service->lend($this->ana, [$this->kit['ARD-0001'], $this->kit['ARD-0002']], 'mentor-1');

        try {
            $this->service->lend($this->ana, [$this->kit['SEN-0001']], 'mentor-1');
        } catch (ParticipantLimitExceeded) {
        }
        $this->assertCount(2, $this->participants->locked, 'cada préstamo bloquea antes de contar');
    }

    // ---------- Datos que no existen ----------

    public function test_an_unknown_participant_is_not_found(): void
    {
        $this->expectException(NotFound::class);
        $this->service->lend('no-existe', [$this->kit['ARD-0001']], 'mentor-1');
    }

    public function test_an_unknown_kit_is_not_found(): void
    {
        $this->expectException(NotFound::class);
        $this->service->lend($this->ana, ['no-existe'], 'mentor-1');
    }
}
