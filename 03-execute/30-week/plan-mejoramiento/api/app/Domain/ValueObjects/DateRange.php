<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\InvalidDateRange;
use App\Domain\Errors\ValidationError;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Rango de días calendario [from, to], ambos incluidos, en UTC (CA-08.2, CA-10.2).
 *
 * Como loaned_at es un instante, el rango se traduce a instantes (plan.md H-06):
 * desde `from 00:00Z` (incluido) hasta `to + 1 día 00:00Z` (excluido).
 */
final readonly class DateRange
{
    public DateTimeImmutable $from;

    public DateTimeImmutable $to;

    public function __construct(DateTimeImmutable $from, DateTimeImmutable $to)
    {
        $from = self::startOfDayUtc($from);
        $to = self::startOfDayUtc($to);
        if ($to < $from) {
            throw InvalidDateRange::endBeforeStart();
        }
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * Arma el rango desde dos fechas de calendario «AAAA-MM-DD» (los `from` y `to` de la API), leídas siempre en UTC.
     * No depende de la zona horaria del servidor: «2026-11-01» es el 1 de noviembre en cualquier máquina.
     * Una fecha mal escrita o inexistente (2026-02-30) → VALIDATION_ERROR en ese campo.
     */
    public static function fromDates(string $from, string $to): self
    {
        return new self(self::parseDate($from, 'from'), self::parseDate($to, 'to'));
    }

    /** Primer instante incluido. */
    public function startsAt(): DateTimeImmutable
    {
        return $this->from;
    }

    /** Primer instante que ya queda fuera del rango. */
    public function endsBefore(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $instant >= $this->startsAt() && $instant < $this->endsBefore();
    }

    private static function parseDate(string $value, string $field): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value), new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== trim($value)) {
            throw ValidationError::forField($field, 'fecha AAAA-MM-DD', "La fecha «{$value}» no es válida; usa el formato AAAA-MM-DD.");
        }

        return $date;
    }

    private static function startOfDayUtc(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
    }
}
