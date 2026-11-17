<?php

namespace App\Application\Services;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Results\LoanFilter;
use App\Application\Results\LoanView;
use App\Domain\Errors\ValidationError;
use App\Domain\Loan;
use App\Domain\LoanStatus;
use App\Domain\ValueObjects\DateRange;

/**
 * HU-08 · Listar préstamos por rango de fechas, estado y participante, del más reciente al más antiguo, paginado (CA-08.2).
 * El rango ya llega validado como DateRange: fin antes que inicio → INVALID_DATE_RANGE (lo lanza DateRange).
 */
final class ListLoansService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {}

    /** @return Page<LoanView> */
    public function list(?DateRange $range, ?string $status, ?string $participantId, PageRequest $page): Page
    {
        $filter = new LoanFilter($range, self::status($status), $participantId);
        $now = $this->clock->now();

        $found = $this->loans->search($filter, $page, $now);

        return new Page(
            array_map(fn (Loan $loan) => new LoanView($loan, $loan->status($now)), $found->items),
            $found->page,
            $found->size,
            $found->total,
        );
    }

    private static function status(?string $status): ?LoanStatus
    {
        if ($status === null || trim($status) === '') {
            return null;
        }

        return LoanStatus::tryFrom(trim($status))
            ?? throw ValidationError::forField('status', 'open, overdue o closed', 'El estado debe ser open, overdue o closed.');
    }
}
