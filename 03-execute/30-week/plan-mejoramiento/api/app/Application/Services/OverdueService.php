<?php

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Results\OverdueLine;

/**
 * HU-09 · Kits vencidos: líneas sin devolver con due_at anterior a ahora (CA-09.1),
 * con días completos de retraso, de más días a menos (CA-09.2).
 */
final class OverdueService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {}

    /** @return list<OverdueLine> */
    public function list(): array
    {
        $now = $this->clock->now();

        $lines = array_map(
            fn (OverdueLine $line) => $line->withDaysLate($line->item->daysOverdue($now)),
            $this->loans->overdueLines($now),
        );
        // Más días primero; con los mismos días, el que venció antes
        usort($lines, fn (OverdueLine $a, OverdueLine $b) => [$b->daysLate, $a->item->dueAt] <=> [$a->daysLate, $b->item->dueAt]);

        return $lines;
    }
}
