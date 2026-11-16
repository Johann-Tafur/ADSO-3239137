<?php

namespace App\Domain\ValueObjects;

use App\Domain\Errors\InvalidDateRange;
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

    private static function startOfDayUtc(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
    }
}
