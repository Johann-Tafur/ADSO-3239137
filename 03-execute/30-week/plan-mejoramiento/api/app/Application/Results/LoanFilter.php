<?php

namespace App\Application\Results;

use App\Domain\LoanStatus;
use App\Domain\ValueObjects\DateRange;

/** Filtros del listado de préstamos (CA-08.2). Un filtro en null no filtra. */
final readonly class LoanFilter
{
    public function __construct(
        public ?DateRange $range = null,
        public ?LoanStatus $status = null,
        public ?string $participantId = null,
    ) {}
}
