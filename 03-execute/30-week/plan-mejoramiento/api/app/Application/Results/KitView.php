<?php

namespace App\Application\Results;

use App\Domain\Category;
use App\Domain\Kit;

/** Un kit con su categoría, como lo pide la respuesta de api-contract.md §3.4. */
final readonly class KitView
{
    public function __construct(
        public Kit $kit,
        public Category $category,
    ) {}
}
