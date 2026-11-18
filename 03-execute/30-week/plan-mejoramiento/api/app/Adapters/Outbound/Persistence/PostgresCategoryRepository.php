<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\CategoryRepository;
use App\Domain\Category;
use Illuminate\Database\ConnectionInterface;

/** CategoryRepository sobre robotics.category: las cinco categorías que siembra la migración. */
final class PostgresCategoryRepository implements CategoryRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function all(): array
    {
        return array_map(
            fn (object $row) => new Category($row->id, $row->name),
            $this->db->select('SELECT id, name FROM category ORDER BY name '.Sql::HUMAN_ORDER),
        );
    }

    public function find(string $id): ?Category
    {
        if (! Uuid::isValid($id)) {
            return null;
        }
        $row = $this->db->selectOne('SELECT id, name FROM category WHERE id = ?', [$id]);

        return $row === null ? null : new Category($row->id, $row->name);
    }
}
