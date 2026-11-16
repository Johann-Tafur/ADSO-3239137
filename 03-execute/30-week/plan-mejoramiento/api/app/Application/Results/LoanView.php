<?php

namespace App\Application\Results;

use App\Domain\Loan;
use App\Domain\LoanStatus;

/** Un préstamo con su estado ya calculado con el Clock (CA-08.3), listo para la respuesta de api-contract.md §3.6. */
final readonly class LoanView
{
    public function __construct(
        public Loan $loan,
        public LoanStatus $status,
    ) {}
}
