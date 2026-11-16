<?php

namespace Tests\Unit\Domain;

use App\Domain\Errors\KitHasOpenLoans;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Errors\TotalBelowLoaned;
use App\Domain\Errors\ValidationError;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

/** T-04: el kit nunca baja de 0 disponibles ni pasa del total (RN-1). */
final class KitTest extends TestCase
{
    private const SENSORS = '22222222-2222-4222-8222-222222222222';

    private function kit(int $total = 3): Kit
    {
        return Kit::create(
            'a1b2c3d4-0000-4000-8000-000000000001',
            '  Arduino Starter Kit ',
            'Arduino',
            new KitReference('ARD-0001'),
            self::SENSORS,
            new Units($total),
        );
    }

    // ---------- Alta ----------

    public function test_a_new_kit_starts_with_all_units_available_and_version_one(): void
    {
        $kit = $this->kit(3);

        $this->assertSame(3, $kit->totalUnits());
        $this->assertSame(3, $kit->availableUnits());
        $this->assertSame(1, $kit->version());
        $this->assertNull($kit->photoKey());
        $this->assertTrue($kit->isActive());
        $this->assertSame('Arduino Starter Kit', $kit->name(), 'el nombre se recorta');
    }

    public function test_an_empty_name_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        Kit::create('id', '   ', 'Arduino', new KitReference('ARD-0001'), self::SENSORS, new Units(1));
    }

    // ---------- Prestar y devolver ----------

    public function test_withdraw_takes_one_unit(): void
    {
        $kit = $this->kit(3);
        $kit->withdraw();

        $this->assertSame(2, $kit->availableUnits());
        $this->assertSame(1, $kit->loanedUnits());
    }

    public function test_withdraw_never_goes_below_zero(): void
    {
        $kit = $this->kit(1);
        $kit->withdraw();

        try {
            $kit->withdraw();
            $this->fail('Debió rechazar el préstamo sin unidades');
        } catch (KitNotAvailable $error) {
            $this->assertSame('KIT_NOT_AVAILABLE', $error->errorCode());
            $this->assertStringContainsString('Arduino Starter Kit', $error->getMessage());
        }
        $this->assertSame(0, $kit->availableUnits());
    }

    public function test_a_removed_kit_cannot_be_withdrawn(): void
    {
        $kit = $this->kit(2);
        $kit->remove(new DateTimeImmutable('2026-11-16T12:00:00Z'));

        $this->expectException(KitNotAvailable::class);
        $kit->withdraw();
    }

    public function test_restock_gives_the_unit_back(): void
    {
        $kit = $this->kit(2);
        $kit->withdraw();
        $kit->restock();

        $this->assertSame(2, $kit->availableUnits());
    }

    public function test_restock_never_goes_above_the_total(): void
    {
        $kit = $this->kit(2);

        $this->expectException(LogicException::class);
        $kit->restock();
    }

    // ---------- Cambio de total (CA-03.3) ----------

    public function test_raising_the_total_raises_available_by_the_same_difference(): void
    {
        $kit = $this->kit(3);
        $kit->withdraw();
        $kit->changeTotal(new Units(5));

        $this->assertSame(5, $kit->totalUnits());
        $this->assertSame(4, $kit->availableUnits());
    }

    public function test_lowering_the_total_down_to_the_loaned_units_leaves_zero_available(): void
    {
        $kit = $this->kit(4);
        $kit->withdraw();
        $kit->withdraw();
        $kit->changeTotal(new Units(2));

        $this->assertSame(2, $kit->totalUnits());
        $this->assertSame(0, $kit->availableUnits());
    }

    public function test_the_total_cannot_go_below_the_loaned_units(): void
    {
        $kit = $this->kit(4);
        $kit->withdraw();
        $kit->withdraw();

        try {
            $kit->changeTotal(new Units(1));
            $this->fail('Debió rechazar un total menor que lo prestado');
        } catch (TotalBelowLoaned $error) {
            $this->assertSame('TOTAL_BELOW_LOANED', $error->errorCode());
        }
        $this->assertSame(4, $kit->totalUnits());
        $this->assertSame(2, $kit->availableUnits());
    }

    // ---------- Baja lógica (CA-03.5, ADR-003) ----------

    public function test_remove_marks_the_kit_with_the_instant(): void
    {
        $now = new DateTimeImmutable('2026-11-16T12:00:00Z');
        $kit = $this->kit(2);
        $kit->remove($now);

        $this->assertFalse($kit->isActive());
        $this->assertEquals($now, $kit->deletedAt());
    }

    public function test_a_kit_with_loaned_units_cannot_be_removed(): void
    {
        $kit = $this->kit(2);
        $kit->withdraw();

        try {
            $kit->remove(new DateTimeImmutable('2026-11-16T12:00:00Z'));
            $this->fail('Debió rechazar la baja con préstamos abiertos');
        } catch (KitHasOpenLoans $error) {
            $this->assertSame('KIT_HAS_OPEN_LOANS', $error->errorCode());
        }
        $this->assertTrue($kit->isActive());
    }

    public function test_removing_twice_keeps_the_first_instant(): void
    {
        $first = new DateTimeImmutable('2026-11-16T12:00:00Z');
        $kit = $this->kit(1);
        $kit->remove($first);
        $kit->remove(new DateTimeImmutable('2026-11-17T12:00:00Z'));

        $this->assertEquals($first, $kit->deletedAt());
    }

    // ---------- Edición y foto ----------

    public function test_update_details_replaces_the_descriptive_data(): void
    {
        $kit = $this->kit(1);
        $kit->updateDetails(' Arduino Mega ', 'Arduino', new KitReference('ARD-0002'), '11111111-1111-4111-8111-111111111111');

        $this->assertSame('Arduino Mega', $kit->name());
        $this->assertSame('ARD-0002', $kit->reference()->value);
        $this->assertSame('11111111-1111-4111-8111-111111111111', $kit->categoryId());
    }

    public function test_photo_key_is_never_an_empty_string(): void
    {
        $kit = $this->kit(1);
        $kit->attachPhoto('photos/abc123.png');
        $this->assertSame('photos/abc123.png', $kit->photoKey());

        $kit->detachPhoto();
        $this->assertNull($kit->photoKey());

        $this->expectException(LogicException::class);
        $kit->attachPhoto('  ');
    }

    // ---------- Reconstrucción desde la base ----------

    public function test_restore_rejects_units_out_of_range(): void
    {
        $this->expectException(LogicException::class);
        Kit::restore('id', 'Kit', 'Fab', new KitReference('ARD-0001'), self::SENSORS, 2, 3, null, 7, null);
    }

    public function test_restore_keeps_the_version_read(): void
    {
        $kit = Kit::restore('id', 'Kit', 'Fab', new KitReference('ARD-0001'), self::SENSORS, 2, 1, null, 7, null);

        $this->assertSame(7, $kit->version());
        $this->assertSame(1, $kit->loanedUnits());
    }
}
