<?php

namespace App\Application\Ports;

use App\Domain\Loan;
use App\Domain\LoanItem;

/** Guarda y lee préstamos. El adaptador real (T-13) usa las tablas robotics.loan y robotics.loan_item. */
interface LoanRepository
{
    /** Id nuevo (UUID) para un préstamo o una línea que todavía no existe. */
    public function nextId(): string;

    /**
     * Las líneas sin devolver de un participante, de todos sus préstamos (consulta Q7 de data-model.md).
     * Con ellas se cuentan los kits en su poder (RN-2) y se ven sus vencidos (RN-3).
     *
     * @return list<LoanItem>
     */
    public function openItemsOf(string $participantId): array;

    /** Guarda un préstamo nuevo con todas sus líneas. Un préstamo no se edita ni se borra (CA-06.7). */
    public function add(Loan $loan): void;
}
