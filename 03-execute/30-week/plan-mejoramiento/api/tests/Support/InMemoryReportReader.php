<?php

namespace Tests\Support;

use App\Application\Ports\ReportReader;
use App\Application\Results\ReportRow;
use App\Domain\ValueObjects\DateRange;

/** Fake de ReportReader: agrupa las líneas del fake de préstamos igual que el GROUP BY de la consulta real. */
final class InMemoryReportReader implements ReportReader
{
    public function __construct(private readonly InMemoryLoanRepository $loans) {}

    public function mostRequested(DateRange $range): array
    {
        /** @var array<string, ReportRow> $rows */
        $rows = [];
        foreach ($this->loans->all() as $loan) {
            if (! $range->contains($loan->loanedAt())) {
                continue;
            }
            foreach ($loan->items() as $item) {
                $key = "{$item->kitId}|{$item->kitName}|{$item->categoryName}";
                $times = isset($rows[$key]) ? $rows[$key]->timesRequested + 1 : 1;
                $rows[$key] = new ReportRow($item->kitId, $item->kitName, $item->categoryName, $times);
            }
        }

        return array_values($rows);
    }
}
