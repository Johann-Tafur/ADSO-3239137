<?php

namespace App\Application\Results;

use App\Domain\ValueObjects\DateRange;

/** El reporte completo: el rango pedido y sus filas ya ordenadas (api-contract.md §3.7). */
final readonly class MostRequestedReport
{
    /** @param list<ReportRow> $rows */
    public function __construct(
        public DateRange $range,
        public array $rows,
    ) {}
}
