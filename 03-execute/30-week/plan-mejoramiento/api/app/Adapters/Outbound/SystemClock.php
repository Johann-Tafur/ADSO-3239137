<?php

namespace App\Adapters\Outbound;

use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

/** El reloj real, siempre en UTC (RN-6). En las pruebas se cambia por FixedClock (plan D-05). */
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
