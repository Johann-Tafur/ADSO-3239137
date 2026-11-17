<?php

namespace App\Application\Services;

use App\Application\Ports\Clock;
use App\Application\Ports\KitRepository;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\TransactionRunner;
use App\Domain\Errors\NotFound;
use App\Domain\LoanItem;
use LogicException;

/**
 * HU-07 · Devolver una línea (POST /loans/{loan_id}/items/{item_id}/return).
 *
 * - Pone returned_at y suma 1 al kit en la misma transacción (CA-07.1).
 * - Ya devuelta → ITEM_ALREADY_RETURNED y el kit no se toca (CA-07.2). Si dos la devuelven a la vez,
 *   markReturned() deja pasar solo a una (WHERE returned_at IS NULL) y la otra recibe el mismo error.
 * - Las disponibles nunca superan el total: lo cuida Kit::restock() y, al final, ck_kit_available_range (CA-07.3).
 */
final class ReturnItemService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly KitRepository $kits,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
    ) {}

    public function returnItem(string $loanId, string $itemId): LoanItem
    {
        return $this->transaction->run(function () use ($loanId, $itemId) {
            $loan = $this->loans->find($loanId) ?? throw NotFound::resource('el préstamo');
            $item = $loan->item($itemId);

            // Un kit con unidades prestadas no se puede dar de baja (CA-03.5), así que debe estar activo
            $kit = $this->kits->findActive($item->kitId)
                ?? throw new LogicException("La línea {$itemId} apunta a un kit que no está activo.");

            $returned = $loan->returnItem($itemId, $kit, $this->clock->now());

            // Primero la línea: si otra petición ya la devolvió, aquí se detiene y el kit no se guarda
            $this->loans->markReturned($returned);
            $this->kits->save($kit);

            return $returned;
        });
    }
}
