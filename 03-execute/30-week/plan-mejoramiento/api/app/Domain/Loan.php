<?php

namespace App\Domain;

use App\Domain\Errors\DuplicateKitInLoan;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Errors\KitNotAvailable;
use App\Domain\Errors\NotFound;
use App\Domain\Errors\ValidationError;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;

/**
 * Préstamo: hecho consumado de un participante, registrado por un usuario (HU-06, HU-07, HU-08).
 *
 * - Trae de 1 a 2 líneas, sin kits repetidos (CA-06.1, CA-06.2).
 * - Cada línea vence a los 12 días exactos, en UTC (CA-06.6, RN-6) y descuenta una unidad del kit (CA-06.3).
 * - Es inmutable salvo devolver una línea (CA-06.7).
 * - El estado se calcula con la hora que se le pase; no se guarda (CA-08.3).
 *
 * Regla de todos los métodos que cambian algo: primero se comprueba TODO y solo después se modifica.
 * Si algo falla, ni el préstamo ni los kits quedan a medias.
 *
 * Las reglas que miran otros préstamos (máximo 2 sin devolver, vencidos) las aplica LendKitsService (T-09).
 */
final class Loan
{
    public const LOAN_DAYS = 12;

    public const MAX_ITEMS = 2;

    /** @param list<LoanItem> $items */
    private function __construct(
        private readonly string $id,
        private readonly string $participantId,
        private readonly string $loanedByUserId,
        private readonly DateTimeImmutable $loanedAt,
        private array $items,
    ) {}

    /**
     * Registra un préstamo nuevo y descuenta una unidad de cada kit.
     *
     * Cada línea trae la categoría del kit (id y nombre) para congelar su nombre;
     * el id debe ser el del kit, así no se congela una categoría equivocada.
     *
     * @param list<array{item_id: string, kit: Kit, category_id: string, category_name: string}> $lines
     */
    public static function lend(
        string $id,
        string $participantId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $lines,
    ): self {
        // 1. Comprobar todo, sin tocar nada
        if (count($lines) < 1 || count($lines) > self::MAX_ITEMS) {
            throw ValidationError::forField(
                'items',
                'entre 1 y '.self::MAX_ITEMS.' kits',
                'Un préstamo lleva de 1 a '.self::MAX_ITEMS.' kits.',
            );
        }

        $kitIds = [];
        $itemIds = [];
        foreach ($lines as $line) {
            $kit = $line['kit'];
            if (isset($kitIds[$kit->id()])) {
                throw DuplicateKitInLoan::kit($kit->id());
            }
            if (isset($itemIds[$line['item_id']])) {
                throw new LogicException("Dos líneas con el mismo id {$line['item_id']}.");
            }
            if ($line['category_id'] !== $kit->categoryId()) {
                throw new LogicException("La categoría {$line['category_id']} no es la del kit {$kit->id()}.");
            }
            if (! $kit->canWithdraw()) {
                throw KitNotAvailable::named($kit->name());
            }
            $kitIds[$kit->id()] = true;
            $itemIds[$line['item_id']] = true;
        }

        // 2. Ya es seguro: descontar unidades y crear las líneas
        $loanedAt = $loanedAt->setTimezone(new DateTimeZone('UTC'));
        $dueAt = $loanedAt->modify('+'.self::LOAN_DAYS.' days');
        $items = [];
        foreach ($lines as $line) {
            $kit = $line['kit'];
            $kit->withdraw();
            $items[] = new LoanItem(
                $line['item_id'],
                $kit->id(),
                $kit->name(),
                $kit->reference()->value,
                $line['category_name'],
                $dueAt,
                null,
            );
        }

        return new self($id, $participantId, $loanedByUserId, $loanedAt, $items);
    }

    /**
     * Reconstruye un préstamo leído de la base. Lo usa solo el repositorio.
     * Un préstamo sin líneas o con más de 2 indica un error de lectura: falla en vez de esconderlo.
     *
     * @param list<LoanItem> $items
     */
    public static function restore(
        string $id,
        string $participantId,
        string $loanedByUserId,
        DateTimeImmutable $loanedAt,
        array $items,
    ): self {
        if (count($items) < 1 || count($items) > self::MAX_ITEMS) {
            throw new LogicException("Préstamo {$id} leído con ".count($items).' líneas.');
        }

        return new self($id, $participantId, $loanedByUserId, $loanedAt, array_values($items));
    }

    /**
     * Devuelve una línea: le pone returned_at y suma la unidad al kit, en el mismo paso (CA-07.1).
     * Devolverla otra vez lanza ITEM_ALREADY_RETURNED y el kit no se toca (CA-07.2).
     *
     * El kit debe ser el de la línea: el servicio lo busca con $loan->item($id)->kitId.
     * Si no coincide es un error de programación, no del usuario.
     */
    public function returnItem(string $itemId, Kit $kit, DateTimeImmutable $now): LoanItem
    {
        // 1. Comprobar todo, sin tocar nada
        $position = $this->positionOf($itemId);
        $item = $this->items[$position];
        if ($item->isReturned()) {
            throw ItemAlreadyReturned::item($itemId);
        }
        if ($item->kitId !== $kit->id()) {
            throw new LogicException("La línea {$itemId} no es del kit {$kit->id()}.");
        }
        if (! $kit->canRestock()) {
            throw new LogicException("Kit {$kit->id()}: devolver superaría el total de unidades.");
        }

        // 2. Cambiar las dos cosas juntas
        $kit->restock();
        $returned = $item->withReturnedAt($now->setTimezone(new DateTimeZone('UTC')));
        $this->items[$position] = $returned;

        return $returned;
    }

    /** closed si todo está devuelto; overdue si alguna línea sin devolver ya venció; si no, open (CA-07.4, CA-08.1). */
    public function status(DateTimeImmutable $now): LoanStatus
    {
        $allReturned = true;
        foreach ($this->items as $item) {
            if ($item->isOverdue($now)) {
                return LoanStatus::Overdue;
            }
            $allReturned = $allReturned && $item->isReturned();
        }

        return $allReturned ? LoanStatus::Closed : LoanStatus::Open;
    }

    public function item(string $itemId): LoanItem
    {
        return $this->items[$this->positionOf($itemId)];
    }

    /** @return list<LoanItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function participantId(): string
    {
        return $this->participantId;
    }

    public function loanedByUserId(): string
    {
        return $this->loanedByUserId;
    }

    public function loanedAt(): DateTimeImmutable
    {
        return $this->loanedAt;
    }

    private function positionOf(string $itemId): int
    {
        foreach ($this->items as $position => $item) {
            if ($item->id === $itemId) {
                return $position;
            }
        }
        throw NotFound::resource('la línea del préstamo');
    }
}
