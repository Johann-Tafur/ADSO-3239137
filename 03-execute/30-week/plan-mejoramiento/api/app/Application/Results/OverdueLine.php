<?php

namespace App\Application\Results;

use App\Domain\LoanItem;

/**
 * Una línea vencida con lo que muestra GET /loans/overdue (api-contract.md §3.6).
 * El repositorio la entrega con daysLate en 0; OverdueService calcula los días con el Clock.
 * Aquí sí va el nombre del participante: es un listado operativo, no el reporte (CA-10.3).
 */
final readonly class OverdueLine
{
    public function __construct(
        public string $loanId,
        public LoanItem $item,
        public string $participantName,
        public int $daysLate = 0,
    ) {}

    public function withDaysLate(int $daysLate): self
    {
        return new self($this->loanId, $this->item, $this->participantName, $daysLate);
    }
}
