<?php

namespace App\Application\Ports;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Results\LoanFilter;
use App\Application\Results\OverdueLine;
use App\Domain\Loan;
use App\Domain\LoanItem;
use DateTimeImmutable;

/** Guarda y lee préstamos. El adaptador real (T-13) usa las tablas robotics.loan y robotics.loan_item. */
interface LoanRepository
{
    /** Id nuevo (UUID) para un préstamo o una línea que todavía no existe. */
    public function nextId(): string;

    public function find(string $id): ?Loan;

    /**
     * Las líneas sin devolver de un participante, de todos sus préstamos (consulta Q7 de data-model.md).
     * Con ellas se cuentan los kits en su poder (RN-2) y se ven sus vencidos (RN-3).
     *
     * @return list<LoanItem>
     */
    public function openItemsOf(string $participantId): array;

    /**
     * Préstamos por rango, estado y participante, del más reciente al más antiguo (CA-08.2).
     * El estado se calcula con $now, igual que Loan::status() (CA-08.3).
     *
     * @return Page<Loan>
     */
    public function search(LoanFilter $filter, PageRequest $page, DateTimeImmutable $now): Page;

    /**
     * Líneas sin devolver con due_at anterior a $now, con el nombre del participante (CA-09.1).
     *
     * @return list<OverdueLine>
     */
    public function overdueLines(DateTimeImmutable $now): array;

    /** Guarda un préstamo nuevo con todas sus líneas. Un préstamo no se edita ni se borra (CA-06.7). */
    public function add(Loan $loan): void;

    /**
     * Guarda la devolución de una línea: UPDATE loan_item SET returned_at = ? WHERE id = ? AND returned_at IS NULL.
     *
     * @throws \App\Domain\Errors\ItemAlreadyReturned si otra petición la devolvió primero (0 filas, CA-07.2)
     */
    public function markReturned(LoanItem $item): void;
}
