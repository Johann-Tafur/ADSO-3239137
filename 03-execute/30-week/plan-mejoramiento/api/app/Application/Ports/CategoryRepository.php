<?php

namespace App\Application\Ports;

use App\Domain\Category;

/** Lee las cinco categorías fijas. Solo lectura: no se crean ni se editan. */
interface CategoryRepository
{
    /** @return list<Category> ordenadas por nombre */
    public function all(): array;

    public function find(string $id): ?Category;
}
