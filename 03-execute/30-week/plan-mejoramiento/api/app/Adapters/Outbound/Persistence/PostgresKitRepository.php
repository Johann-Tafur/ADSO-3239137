<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Paging\Page;
use App\Application\Paging\PageRequest;
use App\Application\Ports\KitRepository;
use App\Application\Results\KitView;
use App\Domain\Category;
use App\Domain\Errors\KitChanged;
use App\Domain\Errors\ReferenceTaken;
use App\Domain\Kit;
use App\Domain\ValueObjects\KitReference;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/**
 * KitRepository sobre robotics.kit.
 *
 * - Toda consulta del catálogo filtra deleted_at IS NULL (ADR-003).
 * - save() es la concurrencia optimista de ADR-002: UPDATE … WHERE id = ? AND version = ?; 0 filas → KitChanged.
 * - La referencia repetida la frena el índice único parcial uq_kit_reference_active → ReferenceTaken.
 */
final class PostgresKitRepository implements KitRepository
{
    private const COLUMNS = 'k.id, k.name, k.manufacturer, k.reference, k.category_id, k.total_units, k.available_units,
                             k.photo_key, k.version, k.deleted_at';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextId(): string
    {
        return Uuid::new();
    }

    public function findActive(string $id): ?Kit
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->db->selectOne('SELECT '.self::COLUMNS.' FROM kit k WHERE k.id = ? AND k.deleted_at IS NULL', [$id]);

        return $row === null ? null : self::toKit($row);
    }

    public function findActiveByReference(KitReference $reference): ?Kit
    {
        $row = $this->db->selectOne(
            'SELECT '.self::COLUMNS.' FROM kit k WHERE k.reference = ? AND k.deleted_at IS NULL',
            [$reference->value],
        );

        return $row === null ? null : self::toKit($row);
    }

    public function search(?string $text, ?string $categoryId, PageRequest $page): Page
    {
        if ($categoryId !== null && ! Uuid::isValid($categoryId)) {
            return new Page([], $page->page, $page->size, 0);
        }

        // Filtros comunes al conteo y a la página. Con categoría, ix_kit_category_name_active atiende el filtro.
        $where = ['k.deleted_at IS NULL'];
        $bindings = [];
        if ($categoryId !== null) {
            $where[] = 'k.category_id = ?';
            $bindings[] = $categoryId;
        }
        if ($text !== null) {
            $where[] = '(k.name ILIKE ? OR k.manufacturer ILIKE ?)';
            $bindings[] = Sql::contains($text);
            $bindings[] = Sql::contains($text);
        }
        $where = implode(' AND ', $where);

        $total = (int) $this->db->selectOne("SELECT count(*) AS total FROM kit k WHERE {$where}", $bindings)->total;
        $rows = $this->db->select(
            'SELECT '.self::COLUMNS.', c.name AS category_name
               FROM kit k JOIN category c ON c.id = k.category_id
              WHERE '.$where.'
              ORDER BY k.name '.Sql::HUMAN_ORDER.', k.id
              LIMIT ? OFFSET ?',
            [...$bindings, $page->size, $page->offset()],
        );

        $items = array_map(
            fn (object $row) => new KitView(self::toKit($row), new Category($row->category_id, $row->category_name)),
            $rows,
        );

        return new Page($items, $page->page, $page->size, $total);
    }

    public function add(Kit $kit): void
    {
        try {
            $this->db->insert(
                'INSERT INTO kit (id, name, manufacturer, reference, category_id, total_units, available_units,
                                  photo_key, version, deleted_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $kit->id(), $kit->name(), $kit->manufacturer(), $kit->reference()->value, $kit->categoryId(),
                    $kit->totalUnits(), $kit->availableUnits(), $kit->photoKey(), $kit->version(),
                    $kit->deletedAt() === null ? null : Sql::toDb($kit->deletedAt()),
                ],
            );
        } catch (QueryException $error) {
            throw self::translate($error, $kit);
        }
    }

    public function save(Kit $kit): void
    {
        try {
            $affected = $this->db->update(
                'UPDATE kit
                    SET name = ?, manufacturer = ?, reference = ?, category_id = ?, total_units = ?,
                        available_units = ?, photo_key = ?, deleted_at = ?, version = version + 1
                  WHERE id = ? AND version = ?',
                [
                    $kit->name(), $kit->manufacturer(), $kit->reference()->value, $kit->categoryId(),
                    $kit->totalUnits(), $kit->availableUnits(), $kit->photoKey(),
                    $kit->deletedAt() === null ? null : Sql::toDb($kit->deletedAt()),
                    $kit->id(), $kit->version(),
                ],
            );
        } catch (QueryException $error) {
            throw self::translate($error, $kit);
        }

        if ($affected === 0) {
            // Otra petición guardó primero: la versión leída ya no es la de la base (ADR-002)
            throw KitChanged::kit($kit->name());
        }
    }

    private static function translate(QueryException $error, Kit $kit): \Throwable
    {
        return Sql::violatesUnique($error, 'uq_kit_reference_active')
            ? ReferenceTaken::reference($kit->reference()->value)
            : $error;
    }

    private static function toKit(object $row): Kit
    {
        return Kit::restore(
            $row->id,
            $row->name,
            $row->manufacturer,
            new KitReference($row->reference),
            $row->category_id,
            (int) $row->total_units,
            (int) $row->available_units,
            $row->photo_key,
            (int) $row->version,
            Sql::fromDb($row->deleted_at),
        );
    }
}
