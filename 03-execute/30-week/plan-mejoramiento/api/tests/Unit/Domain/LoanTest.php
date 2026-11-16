<?php

namespace Tests\Unit\Domain;

use App\Domain\Errors\DuplicateKitInLoan;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use App\Domain\Kit;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\LoanStatus;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;

/** T-05: préstamo con estado calculado, 12 días y máximo 2 líneas. El tiempo lo controla FixedClock. */
final class LoanTest extends TestCase
{
    private const CATEGORY = 'cat-1';

    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-16T15:00:00Z');
    }

    private function kit(string $id, string $reference, int $total = 2): Kit
    {
        return Kit::create($id, "Kit {$reference}", 'Fabricante', new KitReference($reference), self::CATEGORY, new Units($total));
    }

    /** @return array{item_id: string, kit: Kit, category_id: string, category_name: string} */
    private function line(string $itemId, Kit $kit): array
    {
        return ['item_id' => $itemId, 'kit' => $kit, 'category_id' => self::CATEGORY, 'category_name' => 'Sensores'];
    }

    /** @param list<Kit> $kits */
    private function lend(array $kits, ?DateTimeImmutable $at = null): Loan
    {
        $lines = [];
        foreach ($kits as $i => $kit) {
            $lines[] = $this->line("item-{$i}", $kit);
        }

        return Loan::lend('loan-1', 'participant-1', 'mentor-1', $at ?? $this->clock->now(), $lines);
    }

    // ---------- Registrar (HU-06) ----------

    public function test_each_line_is_due_twelve_days_after_the_loan(): void
    {
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')]);

        $this->assertEquals(new DateTimeImmutable('2026-11-28T15:00:00Z'), $loan->items()[0]->dueAt);
    }

    public function test_the_due_date_is_exactly_288_hours_even_across_a_daylight_saving_change(): void
    {
        // En Nueva York el horario de verano termina el 1-nov-2026: «+12 días» en hora local serían 289 h
        $loanedAt = new DateTimeImmutable('2026-10-25T12:00:00', new \DateTimeZone('America/New_York'));
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')], $loanedAt);

        $hours = ($loan->items()[0]->dueAt->getTimestamp() - $loanedAt->getTimestamp()) / 3600;
        $this->assertSame(288, $hours);
        $this->assertSame('UTC', $loan->loanedAt()->getTimezone()->getName());
    }

    public function test_each_line_takes_one_unit_from_its_kit(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001', 3);
        $drone = $this->kit('k2', 'DRN-0001', 1);
        $this->lend([$arduino, $drone]);

        $this->assertSame(2, $arduino->availableUnits());
        $this->assertSame(0, $drone->availableUnits());
    }

    public function test_a_line_freezes_name_reference_and_category(): void
    {
        $kit = $this->kit('k1', 'ARD-0001');
        $loan = $this->lend([$kit]);

        $kit->updateDetails('Nombre nuevo', 'Otro', new KitReference('ARD-9999'), 'cat-2');

        $item = $loan->items()[0];
        $this->assertSame('Kit ARD-0001', $item->kitName);
        $this->assertSame('ARD-0001', $item->kitReference);
        $this->assertSame('Sensores', $item->categoryName);
    }

    public function test_a_loan_without_lines_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        $this->lend([]);
    }

    public function test_a_loan_with_three_lines_is_rejected_without_touching_the_kits(): void
    {
        $kits = [$this->kit('k1', 'ARD-0001'), $this->kit('k2', 'ARD-0002'), $this->kit('k3', 'ARD-0003')];

        try {
            $this->lend($kits);
            $this->fail('Debió rechazar 3 líneas');
        } catch (ValidationError $error) {
            $this->assertSame('items', $error->details()[0]['field']);
        }
        foreach ($kits as $kit) {
            $this->assertSame(2, $kit->availableUnits());
        }
    }

    public function test_the_same_kit_twice_is_rejected_without_touching_it(): void
    {
        $kit = $this->kit('k1', 'ARD-0001');

        try {
            $this->lend([$kit, $kit]);
            $this->fail('Debió rechazar el kit repetido');
        } catch (DuplicateKitInLoan $error) {
            $this->assertSame('DUPLICATE_KIT_IN_LOAN', $error->errorCode());
        }
        $this->assertSame(2, $kit->availableUnits());
    }

    public function test_if_the_second_kit_has_no_units_the_first_one_is_not_touched(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001', 2);
        $drone = $this->kit('k2', 'DRN-0001', 1);
        $drone->withdraw();

        try {
            $this->lend([$arduino, $drone]);
            $this->fail('Debió rechazar el dron sin unidades');
        } catch (KitNotAvailable $error) {
            $this->assertStringContainsString('DRN-0001', $error->getMessage());
        }
        $this->assertSame(2, $arduino->availableUnits(), 'el Arduino no debió perder su unidad');
    }

    public function test_two_lines_with_the_same_item_id_are_rejected(): void
    {
        $lines = [$this->line('x', $this->kit('k1', 'ARD-0001')), $this->line('x', $this->kit('k2', 'ARD-0002'))];

        $this->expectException(LogicException::class);
        Loan::lend('loan-1', 'participant-1', 'mentor-1', $this->clock->now(), $lines);
    }

    public function test_the_frozen_category_must_be_the_kit_category(): void
    {
        $line = $this->line('item-0', $this->kit('k1', 'ARD-0001'));
        $line['category_id'] = 'otra-categoria';

        $this->expectException(LogicException::class);
        Loan::lend('loan-1', 'participant-1', 'mentor-1', $this->clock->now(), [$line]);
    }

    // ---------- Estado calculado (CA-08.1, CA-08.3) ----------

    public function test_a_new_loan_is_open(): void
    {
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')]);

        $this->assertSame(LoanStatus::Open, $loan->status($this->clock->now()));
    }

    public function test_exactly_at_the_due_instant_it_is_still_open(): void
    {
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')]);
        $this->clock->advance('+12 days');

        $this->assertSame(LoanStatus::Open, $loan->status($this->clock->now()));
    }

    public function test_one_second_after_the_due_instant_it_is_overdue(): void
    {
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')]);
        $this->clock->advance('+12 days');
        $this->clock->advance('+1 second');

        $this->assertSame(LoanStatus::Overdue, $loan->status($this->clock->now()));
        $this->assertSame('overdue', $loan->status($this->clock->now())->value);
    }

    public function test_days_overdue_are_whole_days(): void
    {
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')]);
        $this->clock->advance('+15 days');
        $this->clock->advance('+2 hours');

        $this->assertSame(3, $loan->items()[0]->daysOverdue($this->clock->now()));
    }

    // ---------- Devolver (HU-07) ----------

    public function test_returning_one_of_two_lines_keeps_the_loan_open(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001');
        $loan = $this->lend([$arduino, $this->kit('k2', 'DRN-0001')]);

        $this->clock->advance('+3 days');
        $loan->returnItem('item-0', $arduino, $this->clock->now());

        $this->assertEquals($this->clock->now(), $loan->item('item-0')->returnedAt);
        $this->assertSame(2, $arduino->availableUnits());
        $this->assertSame(LoanStatus::Open, $loan->status($this->clock->now()));
    }

    public function test_returning_every_line_closes_the_loan_even_if_it_was_late(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001');
        $loan = $this->lend([$arduino]);

        $this->clock->advance('+20 days');
        $this->assertSame(LoanStatus::Overdue, $loan->status($this->clock->now()));

        $loan->returnItem('item-0', $arduino, $this->clock->now());
        $this->assertSame(LoanStatus::Closed, $loan->status($this->clock->now()));
    }

    public function test_returning_twice_fails_and_never_adds_two_units(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001', 3);
        $loan = $this->lend([$arduino]);
        $loan->returnItem('item-0', $arduino, $this->clock->now());

        try {
            $loan->returnItem('item-0', $arduino, $this->clock->now());
            $this->fail('Debió rechazar la segunda devolución');
        } catch (ItemAlreadyReturned $error) {
            $this->assertSame('ITEM_ALREADY_RETURNED', $error->errorCode());
        }
        $this->assertSame(3, $arduino->availableUnits());
    }

    public function test_if_the_kit_cannot_take_the_unit_back_the_line_stays_unreturned(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001', 2);
        $loan = $this->lend([$arduino]);
        $arduino->restock(); // simula un kit leído ya completo (dato inconsistente)

        try {
            $loan->returnItem('item-0', $arduino, $this->clock->now());
            $this->fail('Debió rechazar la devolución');
        } catch (LogicException) {
        }
        $this->assertFalse($loan->item('item-0')->isReturned(), 'la línea no debió quedar devuelta');
        $this->assertSame(LoanStatus::Open, $loan->status($this->clock->now()));
    }

    public function test_a_line_cannot_be_returned_from_outside_the_loan(): void
    {
        $loan = $this->lend([$this->kit('k1', 'ARD-0001')]);
        $copy = $loan->item('item-0')->withReturnedAt($this->clock->now());

        $this->assertTrue($copy->isReturned());
        $this->assertFalse($loan->item('item-0')->isReturned(), 'la copia no cambia el préstamo');
    }

    public function test_returning_an_unknown_line_is_not_found(): void
    {
        $arduino = $this->kit('k1', 'ARD-0001');
        $loan = $this->lend([$arduino]);

        $this->expectException(NotFound::class);
        $loan->returnItem('no-existe', $arduino, $this->clock->now());
    }

    // ---------- Reconstrucción desde la base ----------

    public function test_restore_rejects_a_loan_without_lines(): void
    {
        $this->expectException(LogicException::class);
        Loan::restore('loan-1', 'participant-1', 'mentor-1', $this->clock->now(), []);
    }

    public function test_restore_keeps_the_lines_read(): void
    {
        $item = new LoanItem('item-0', 'k1', 'Kit', 'ARD-0001', 'Sensores', $this->clock->now()->modify('+12 days'), null);
        $loan = Loan::restore('loan-1', 'participant-1', 'mentor-1', $this->clock->now(), [$item]);

        $this->assertSame(LoanStatus::Open, $loan->status($this->clock->now()));
    }
}
