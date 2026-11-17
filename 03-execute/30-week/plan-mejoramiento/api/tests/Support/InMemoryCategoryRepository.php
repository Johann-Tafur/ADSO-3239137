<?php

namespace Tests\Support;

use App\Application\Ports\CategoryRepository;
use App\Application\Support\NameOrder;
use App\Domain\Category;

/** Fake de CategoryRepository con las mismas cinco categorías y UUID que siembra la migración. */
final class InMemoryCategoryRepository implements CategoryRepository
{
    public const INICIACION = '11111111-1111-4111-8111-111111111111';

    public const SENSORES = '22222222-2222-4222-8222-222222222222';

    public const DRONES = '33333333-3333-4333-8333-333333333333';

    /** @var array<string, Category> */
    private array $categories;

    public function __construct()
    {
        $this->categories = [];
        foreach ([
            self::INICIACION => 'Iniciación',
            self::SENSORES => 'Sensores',
            self::DRONES => 'Drones',
            '44444444-4444-4444-8444-444444444444' => 'Impresión 3D',
            '55555555-5555-4555-8555-555555555555' => 'Competencia',
        ] as $id => $name) {
            $this->categories[$id] = new Category($id, $name);
        }
    }

    public function all(): array
    {
        $all = array_values($this->categories);
        usort($all, fn (Category $a, Category $b) => NameOrder::compare($a->name, $b->name));

        return $all;
    }

    public function find(string $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }
}
