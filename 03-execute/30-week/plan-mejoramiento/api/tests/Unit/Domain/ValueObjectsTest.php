<?php

namespace Tests\Unit\Domain;

use App\Domain\Errors\InvalidDateRange;
use App\Domain\Errors\ValidationError;
use App\Domain\ValueObjects\DateRange;
use App\Domain\ValueObjects\DocumentNumber;
use App\Domain\ValueObjects\KitReference;
use App\Domain\ValueObjects\Units;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** T-03: objetos de valor del dominio. PHP puro: no arranca Laravel ni toca la base. */
final class ValueObjectsTest extends TestCase
{
    // ---------- KitReference (CA-03.2) ----------

    public function test_a_valid_reference_is_accepted(): void
    {
        $this->assertSame('ARD-0001', (new KitReference('ARD-0001'))->value);
    }

    public function test_surrounding_spaces_are_trimmed(): void
    {
        $this->assertSame('DRN-0420', (new KitReference('  DRN-0420 '))->value);
    }

    /** @return array<string, array{string}> */
    public static function invalidReferences(): array
    {
        return [
            'minúsculas y corta' => ['ard-1'],
            'minúsculas' => ['ard-0001'],
            'sin guion' => ['ARD0001'],
            'dos letras' => ['AR-0001'],
            'tres dígitos' => ['ARD-001'],
            'cinco dígitos' => ['ARD-00012'],
            'letra en los dígitos' => ['ARD-00A1'],
            'vacía' => [''],
        ];
    }

    #[DataProvider('invalidReferences')]
    public function test_an_invalid_reference_is_rejected(string $reference): void
    {
        $this->expectException(ValidationError::class);
        new KitReference($reference);
    }

    public function test_the_error_carries_the_contract_code_and_field(): void
    {
        try {
            new KitReference('ard-1');
            $this->fail('Debió rechazar ard-1');
        } catch (ValidationError $error) {
            $this->assertSame('VALIDATION_ERROR', $error->errorCode());
            $this->assertSame('reference', $error->details()[0]['field']);
            $this->assertStringContainsString('AAA-0000', $error->getMessage());
        }
    }

    public function test_two_references_with_the_same_text_are_equal(): void
    {
        $this->assertTrue((new KitReference('ARD-0001'))->equals(new KitReference(' ARD-0001')));
        $this->assertFalse((new KitReference('ARD-0001'))->equals(new KitReference('ARD-0002')));
    }

    // ---------- Units (CA-03.3) ----------

    public function test_one_unit_is_the_minimum(): void
    {
        $this->assertSame(1, (new Units(1))->value);
        $this->assertSame(40, (new Units(40))->value);
    }

    public function test_zero_units_are_rejected(): void
    {
        $this->expectException(ValidationError::class);
        new Units(0);
    }

    public function test_negative_units_are_rejected(): void
    {
        $this->expectException(ValidationError::class);
        new Units(-3);
    }

    // ---------- DocumentNumber (CA-05.1, data-model §3) ----------

    public function test_a_document_is_trimmed(): void
    {
        $this->assertSame('1023456789', (new DocumentNumber(' 1023456789 '))->value);
    }

    public function test_an_empty_document_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        new DocumentNumber('   ');
    }

    public function test_a_document_longer_than_thirty_characters_is_rejected(): void
    {
        $this->assertSame(30, mb_strlen((new DocumentNumber(str_repeat('9', 30)))->value));

        $this->expectException(ValidationError::class);
        new DocumentNumber(str_repeat('9', 31));
    }

    // ---------- DateRange (CA-10.2, plan H-06) ----------

    public function test_end_equal_to_start_is_a_valid_one_day_range(): void
    {
        $range = new DateRange(new DateTimeImmutable('2026-11-16'), new DateTimeImmutable('2026-11-16'));

        $this->assertEquals(new DateTimeImmutable('2026-11-16T00:00:00Z'), $range->startsAt());
        $this->assertEquals(new DateTimeImmutable('2026-11-17T00:00:00Z'), $range->endsBefore());
    }

    public function test_end_before_start_is_rejected_with_its_own_code(): void
    {
        try {
            new DateRange(new DateTimeImmutable('2026-11-16'), new DateTimeImmutable('2026-11-15'));
            $this->fail('Debió rechazar un rango invertido');
        } catch (InvalidDateRange $error) {
            $this->assertSame('INVALID_DATE_RANGE', $error->errorCode());
        }
    }

    public function test_the_whole_last_day_is_inside_the_range(): void
    {
        $range = new DateRange(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-15'));

        $this->assertTrue($range->contains(new DateTimeImmutable('2026-11-01T00:00:00Z')));
        $this->assertTrue($range->contains(new DateTimeImmutable('2026-11-15T23:59:59Z')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-11-16T00:00:00Z')));
        $this->assertFalse($range->contains(new DateTimeImmutable('2026-10-31T23:59:59Z')));
    }

    public function test_dates_are_taken_in_utc(): void
    {
        // 15-nov 22:00 en Bogotá ya es 16-nov 03:00 en UTC
        $range = new DateRange(
            new DateTimeImmutable('2026-11-15T22:00:00-05:00'),
            new DateTimeImmutable('2026-11-15T22:00:00-05:00'),
        );

        $this->assertEquals(new DateTimeImmutable('2026-11-16T00:00:00Z'), $range->startsAt());
    }

    public function test_calendar_dates_do_not_depend_on_the_server_timezone(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo'); // UTC+9: «2026-11-01» local sería 31-oct en UTC
        try {
            $range = DateRange::fromDates('2026-11-01', '2026-11-30');
        } finally {
            date_default_timezone_set($original);
        }

        $this->assertEquals(new DateTimeImmutable('2026-11-01T00:00:00Z'), $range->startsAt());
        $this->assertEquals(new DateTimeImmutable('2026-12-01T00:00:00Z'), $range->endsBefore());
    }

    public function test_a_badly_written_or_impossible_date_is_a_validation_error(): void
    {
        foreach ([['2026-02-30', 'from'], ['01/11/2026', 'from'], ['', 'from']] as [$bad, $field]) {
            try {
                DateRange::fromDates($bad, '2026-11-30');
                $this->fail("Debió rechazar «{$bad}»");
            } catch (\App\Domain\Errors\ValidationError $error) {
                $this->assertSame($field, $error->details()[0]['field']);
            }
        }
    }

    public function test_from_dates_also_rejects_an_inverted_range(): void
    {
        $this->expectException(InvalidDateRange::class);
        DateRange::fromDates('2026-11-30', '2026-11-01');
    }
}
