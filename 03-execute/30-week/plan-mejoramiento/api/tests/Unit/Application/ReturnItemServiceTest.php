<?php

namespace Tests\Unit\Application;

use App\Application\Services\CreateKitService;
use App\Application\Services\CreateParticipantService;
use App\Application\Services\GetLoanService;
use App\Application\Services\LendKitsService;
use App\Application\Services\ReturnItemService;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\KitChanged;
use App\Domain\Errors\NotFound;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeTransactionRunner;
use Tests\Support\FixedClock;
use Tests\Support\InMemoryCategoryRepository as Categories;
use Tests\Support\InMemoryKitRepository;
use Tests\Support\InMemoryLoanRepository;
use Tests\Support\InMemoryParticipantRepository;

/** T-10 · HU-07: devolver una línea (CA-07.1 a CA-07.4), contra fakes. */
final class ReturnItemServiceTest extends TestCase
{
    private FixedClock $clock;

    private InMemoryKitRepository $kits;

    private InMemoryLoanRepository $loans;

    private FakeTransactionRunner $transaction;

    private ReturnItemService $service;

    private GetLoanService $getLoan;

    private Loan $loan;

    private string $arduino;

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-16T15:00:00Z');
        $categories = new Categories;
        $participants = new InMemoryParticipantRepository;
        $this->kits = new InMemoryKitRepository($categories);
        $this->loans = new InMemoryLoanRepository($participants);
        $this->transaction = new FakeTransactionRunner($this->kits, $this->loans);
        $this->service = new ReturnItemService($this->loans, $this->kits, $this->transaction, $this->clock);
        $this->getLoan = new GetLoanService($this->loans, $this->clock);

        $ana = (new CreateParticipantService($participants))->create('Ana Gómez', '1020304050')->id();
        $createKit = new CreateKitService($this->kits, $categories);
        $this->arduino = $createKit->create('Arduino', 'Arduino', 'ARD-0001', Categories::INICIACION, 3)->kit->id();
        $drone = $createKit->create('Dron', 'DJI', 'DRN-0001', Categories::DRONES, 1)->kit->id();

        $this->loan = (new LendKitsService($this->loans, $this->kits, $participants, $categories, $this->transaction, $this->clock))
            ->lend($ana, [$this->arduino, $drone], 'mentor-1')->loan;
    }

    private function itemOf(int $position): string
    {
        return $this->loan->items()[$position]->id;
    }

    public function test_returning_a_line_sets_returned_at_and_gives_the_unit_back(): void
    {
        $this->clock->advance('+3 days');

        $returned = $this->service->returnItem($this->loan->id(), $this->itemOf(0));

        $this->assertEquals($this->clock->now(), $returned->returnedAt);
        $this->assertSame(3, $this->kits->stored($this->arduino)->availableUnits());
        $this->assertTrue($this->loans->find($this->loan->id())->item($this->itemOf(0))->isReturned());
    }

    public function test_with_one_line_still_out_the_loan_stays_open(): void
    {
        $this->service->returnItem($this->loan->id(), $this->itemOf(0));

        $this->assertSame(LoanStatus::Open, $this->getLoan->get($this->loan->id())->status);
    }

    public function test_returning_every_line_closes_the_loan(): void
    {
        $this->service->returnItem($this->loan->id(), $this->itemOf(0));
        $this->service->returnItem($this->loan->id(), $this->itemOf(1));

        $this->assertSame(LoanStatus::Closed, $this->getLoan->get($this->loan->id())->status);
    }

    public function test_returning_twice_is_item_already_returned_and_never_adds_two_units(): void
    {
        $this->service->returnItem($this->loan->id(), $this->itemOf(0));

        try {
            $this->service->returnItem($this->loan->id(), $this->itemOf(0));
            $this->fail('Debió rechazar la segunda devolución');
        } catch (ItemAlreadyReturned $error) {
            $this->assertSame('ITEM_ALREADY_RETURNED', $error->errorCode());
        }
        $this->assertSame(3, $this->kits->stored($this->arduino)->availableUnits());
    }

    public function test_two_returns_at_the_same_time_add_only_one_unit(): void
    {
        // Las dos peticiones leyeron el préstamo con la línea sin devolver; la primera guarda
        $this->service->returnItem($this->loan->id(), $this->itemOf(0));
        $this->assertSame(3, $this->kits->stored($this->arduino)->availableUnits());

        // La segunda llega con una lectura vieja: markReturned() la frena antes de tocar el kit
        try {
            $this->loans->markReturned($this->loan->item($this->itemOf(0))->withReturnedAt($this->clock->now()));
            $this->fail('Debió frenar la segunda devolución');
        } catch (ItemAlreadyReturned) {
        }
        $this->assertSame(3, $this->kits->stored($this->arduino)->availableUnits());
    }

    public function test_if_saving_the_kit_fails_the_line_stays_unreturned(): void
    {
        $this->kits->failNextSaveOf($this->arduino);

        try {
            $this->service->returnItem($this->loan->id(), $this->itemOf(0));
            $this->fail('Debió fallar al guardar el kit');
        } catch (KitChanged) {
        }
        $this->assertFalse($this->loans->find($this->loan->id())->item($this->itemOf(0))->isReturned(), 'la transacción deshizo la línea');
        $this->assertSame(2, $this->kits->stored($this->arduino)->availableUnits());
    }

    public function test_an_unknown_loan_or_line_is_not_found(): void
    {
        foreach ([['no-existe', $this->itemOf(0)], [$this->loan->id(), 'no-existe']] as [$loanId, $itemId]) {
            try {
                $this->service->returnItem($loanId, $itemId);
                $this->fail('Debió responder NOT_FOUND');
            } catch (NotFound) {
            }
        }
        $this->assertSame(2, $this->kits->stored($this->arduino)->availableUnits());
    }
}
