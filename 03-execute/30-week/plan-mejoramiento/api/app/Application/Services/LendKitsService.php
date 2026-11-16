<?php

namespace App\Application\Services;

use App\Application\Ports\CategoryRepository;
use App\Application\Ports\Clock;
use App\Application\Ports\KitRepository;
use App\Application\Ports\LoanRepository;
use App\Application\Ports\ParticipantRepository;
use App\Application\Ports\TransactionRunner;
use App\Application\Results\LoanView;
use App\Domain\Errors\DuplicateKitInLoan;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ParticipantHasOverdue;
use App\Domain\Errors\ParticipantLimitExceeded;
use App\Domain\Errors\ValidationError;
use App\Domain\Loan;
use LogicException;

/**
 * HU-06 · Registrar un préstamo. Es el flujo de architecture.md §4.
 *
 * Orden de las reglas:
 *  1. Entrada: de 1 a 2 kits, sin repetir (CA-06.1, CA-06.2).
 *  2. Participante: existe; sin vencidos (409, CA-06.5); con lo nuevo no pasa de 2 sin devolver (422, CA-06.4).
 *  3. Kits: existen y tienen unidades (409, CA-06.3). Loan::lend() descuenta y congela los datos (CA-06.6).
 *  4. Guardar kits (con su versión, ADR-002) y el préstamo.
 *
 * Los pasos 2 a 4 van dentro de una transacción: si algo falla, no se registra nada (CA-06.3).
 * Si otro mentor se llevó la última unidad entre la lectura y el guardado, save() lanza KIT_NOT_AVAILABLE (RNF-2).
 */
final class LendKitsService
{
    public function __construct(
        private readonly LoanRepository $loans,
        private readonly KitRepository $kits,
        private readonly ParticipantRepository $participants,
        private readonly CategoryRepository $categories,
        private readonly TransactionRunner $transaction,
        private readonly Clock $clock,
    ) {}

    /** @param list<string> $kitIds */
    public function lend(string $participantId, array $kitIds, string $loanedByUserId): LoanView
    {
        // 1. Entrada: se rechaza antes de tocar la base
        if (count($kitIds) < 1 || count($kitIds) > Loan::MAX_ITEMS) {
            throw ValidationError::forField('items', 'entre 1 y '.Loan::MAX_ITEMS.' kits', 'Un préstamo lleva de 1 a '.Loan::MAX_ITEMS.' kits.');
        }
        $kitIds = array_values($kitIds);
        if (count(array_unique($kitIds)) !== count($kitIds)) {
            throw DuplicateKitInLoan::kit($kitIds[0]); // con máximo 2, si se repite es el primero
        }

        return $this->transaction->run(function () use ($participantId, $kitIds, $loanedByUserId) {
            $now = $this->clock->now();

            // 2. Reglas del participante, que miran sus otros préstamos
            $this->participants->find($participantId) ?? throw NotFound::resource('el participante');
            $openItems = $this->loans->openItemsOf($participantId);
            $overdue = count(array_filter($openItems, fn ($item) => $item->isOverdue($now)));
            if ($overdue > 0) {
                throw ParticipantHasOverdue::withOverdue($overdue);
            }
            if (count($openItems) + count($kitIds) > Loan::MAX_ITEMS) {
                throw ParticipantLimitExceeded::withOpen(count($openItems), Loan::MAX_ITEMS);
            }

            // 3. Kits: el dominio comprueba unidades, descuenta y congela
            $lines = [];
            foreach ($kitIds as $kitId) {
                $kit = $this->kits->findActive($kitId) ?? throw NotFound::resource('el kit');
                $category = $this->categories->find($kit->categoryId())
                    ?? throw new LogicException("El kit {$kitId} apunta a una categoría que no existe.");
                $lines[] = [
                    'item_id' => $this->loans->nextId(),
                    'kit' => $kit,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                ];
            }
            $loan = Loan::lend($this->loans->nextId(), $participantId, $loanedByUserId, $now, $lines);

            // 4. Guardar: cada kit con la versión que se leyó, y después el préstamo
            foreach ($lines as $line) {
                $this->kits->save($line['kit']);
            }
            $this->loans->add($loan);

            return new LoanView($loan, $loan->status($now));
        });
    }
}
