<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\LoanRepository;
use App\Application\Results\LoanFilter;
use App\Application\Results\OverdueLine;
use App\Domain\Errors\ItemAlreadyReturned;
use App\Domain\Loan;
use App\Domain\LoanItem;
use App\Domain\LoanStatus;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * LoanRepository sobre robotics.loan y robotics.loan_item.
 *
 * - Un préstamo se inserta una vez y no se edita (CA-06.7); lo único que cambia es returned_at de una línea.
 * - El estado no se guarda: se calcula en SQL con :now para filtrar, igual que Loan::status() (CA-08.3).
 */
final class PostgresLoanRepository implements LoanRepository
{
    private const ITEM_COLUMNS = 'i.id, i.loan_id, i.kit_id, i.kit_name, i.kit_reference, i.category_name, i.due_at, i.returned_at';

    /** Condiciones del estado calculado, sobre el préstamo «l». Mismas reglas que Loan::status(). */
    private const HAS_OPEN = 'EXISTS (SELECT 1 FROM loan_item o WHERE o.loan_id = l.id AND o.returned_at IS NULL)';

    private const HAS_OVERDUE = 'EXISTS (SELECT 1 FROM loan_item o WHERE o.loan_id = l.id AND o.returned_at IS NULL AND o.due_at < ?)';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextId(): string
    {
        return Uuid::new();
    }

    public function find(string $id): ?Loan
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->db->selectOne('SELECT id, participant_id, loaned_by_user_id, loaned_at FROM loan WHERE id = ?', [$id]);

        return $row === null ? null : $this->withItems([$row])[0];
    }

    public function openItemsOf(string $participantId): array
    {
        if (! Uuid::isValid($participantId)) {
            return [];
        }
        $rows = $this->db->select(
            'SELECT '.self::ITEM_COLUMNS.'
               FROM loan_item i JOIN loan l ON l.id = i.loan_id
              WHERE l.participant_id = ? AND i.returned_at IS NULL',
            [$participantId],
        );

        return array_map(self::toItem(...), $rows);
    }

    public function search(LoanFilter $filter, PageRequest $page, DateTimeImmutable $now): Page
    {
        $where = ['TRUE'];
        $bindings = [];
        if ($filter->range !== null) {
            $where[] = 'l.loaned_at >= ? AND l.loaned_at < ?'; // ix_loan_loaned_at
            $bindings[] = Sql::toDb($filter->range->startsAt());
            $bindings[] = Sql::toDb($filter->range->endsBefore());
        }
        if ($filter->participantId !== null) {
            if (! Uuid::isValid($filter->participantId)) {
                return new Page([], $page->page, $page->size, 0);
            }
            $where[] = 'l.participant_id = ?'; // ix_loan_participant
            $bindings[] = $filter->participantId;
        }
        $nowDb = Sql::toDb($now);
        switch ($filter->status) {
            case LoanStatus::Overdue:
                $where[] = self::HAS_OVERDUE;
                $bindings[] = $nowDb;
                break;
            case LoanStatus::Open:
                $where[] = self::HAS_OPEN.' AND NOT '.self::HAS_OVERDUE;
                $bindings[] = $nowDb;
                break;
            case LoanStatus::Closed:
                $where[] = 'NOT '.self::HAS_OPEN;
                break;
            case null:
                break;
        }
        $where = implode(' AND ', $where);

        $total = (int) $this->db->selectOne("SELECT count(*) AS total FROM loan l WHERE {$where}", $bindings)->total;
        $rows = $this->db->select(
            "SELECT l.id, l.participant_id, l.loaned_by_user_id, l.loaned_at FROM loan l WHERE {$where}
              ORDER BY l.loaned_at DESC, l.id LIMIT ? OFFSET ?",
            [...$bindings, $page->size, $page->offset()],
        );

        return new Page($this->withItems($rows), $page->page, $page->size, $total);
    }

    public function overdueLines(DateTimeImmutable $now): array
    {
        // ix_loan_item_due_open: índice parcial sobre due_at de las líneas sin devolver
        $rows = $this->db->select(
            'SELECT '.self::ITEM_COLUMNS.', p.full_name
               FROM loan_item i
               JOIN loan l ON l.id = i.loan_id
               JOIN participant p ON p.id = l.participant_id
              WHERE i.returned_at IS NULL AND i.due_at < ?
              ORDER BY i.due_at, i.id',
            [Sql::toDb($now)],
        );

        return array_map(fn (object $row) => new OverdueLine($row->loan_id, self::toItem($row), $row->full_name), $rows);
    }

    public function add(Loan $loan): void
    {
        $this->db->insert(
            'INSERT INTO loan (id, participant_id, loaned_by_user_id, loaned_at) VALUES (?, ?, ?, ?)',
            [$loan->id(), $loan->participantId(), $loan->loanedByUserId(), Sql::toDb($loan->loanedAt())],
        );
        foreach ($loan->items() as $item) {
            $this->db->insert(
                'INSERT INTO loan_item (id, loan_id, kit_id, kit_name, kit_reference, category_name, due_at, returned_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $item->id, $loan->id(), $item->kitId, $item->kitName, $item->kitReference, $item->categoryName,
                    Sql::toDb($item->dueAt), $item->returnedAt === null ? null : Sql::toDb($item->returnedAt),
                ],
            );
        }
    }

    public function markReturned(LoanItem $item): void
    {
        if ($item->returnedAt === null) {
            throw new \LogicException("La línea {$item->id} se guarda como devuelta sin returned_at.");
        }
        // Solo cambia si nadie la devolvió antes: dos devoluciones simultáneas → una sola pasa (CA-07.2)
        $affected = $this->db->update(
            'UPDATE loan_item SET returned_at = ? WHERE id = ? AND returned_at IS NULL',
            [Sql::toDb($item->returnedAt), $item->id],
        );
        if ($affected === 0) {
            throw ItemAlreadyReturned::item($item->id);
        }
    }

    /**
     * Arma los préstamos con sus líneas: una consulta para todas las líneas de la página, no una por préstamo.
     *
     * @param list<object> $loanRows
     * @return list<Loan>
     */
    private function withItems(array $loanRows): array
    {
        if ($loanRows === []) {
            return [];
        }
        $ids = array_map(fn (object $row) => $row->id, $loanRows);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $itemRows = $this->db->select(
            'SELECT '.self::ITEM_COLUMNS." FROM loan_item i WHERE i.loan_id IN ({$placeholders})
              ORDER BY i.kit_name ".Sql::HUMAN_ORDER.', i.id',
            $ids,
        );

        $itemsByLoan = [];
        foreach ($itemRows as $row) {
            $itemsByLoan[$row->loan_id][] = self::toItem($row);
        }

        return array_map(fn (object $row) => Loan::restore(
            $row->id,
            $row->participant_id,
            $row->loaned_by_user_id,
            Sql::fromDb($row->loaned_at),
            $itemsByLoan[$row->id] ?? [],
        ), $loanRows);
    }

    private static function toItem(object $row): LoanItem
    {
        return new LoanItem(
            $row->id,
            $row->kit_id,
            $row->kit_name,
            $row->kit_reference,
            $row->category_name,
            Sql::fromDb($row->due_at),
            Sql::fromDb($row->returned_at),
        );
    }
}
