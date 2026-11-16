<?php

namespace Tests\Support;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

/** Reloj de pruebas: marca la hora que se le diga y se adelanta a mano (plan D-05). */
final class FixedClock implements Clock
{
    private DateTimeImmutable $now;

    public function __construct(string $instant)
    {
        $this->now = (new DateTimeImmutable($instant))->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /** Ejemplo: advance('+12 days'), advance('+1 second'). */
    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
