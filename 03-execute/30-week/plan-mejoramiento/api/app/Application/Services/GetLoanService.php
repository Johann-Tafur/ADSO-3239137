<?php

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\LoanRepository;
use App\Application\Results\LoanView;
use App\Domain\Errors\NotFound;

/** HU-08 · Ver un préstamo con sus líneas y su estado calculado (CA-08.1, CA-08.3). */
final class GetLoanService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly Clock $clock,
    ) {}

    public function get(string $id): LoanView
    {
        $loan = $this->loans->find($id) ?? throw NotFound::resource('el préstamo');

        return new LoanView($loan, $loan->status($this->clock->now()));
    }
}
