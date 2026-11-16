<?php

namespace App\Application\Paging;

/**
 * Una página de resultados con el total (CA-04.2). El controlador la convierte en
 * `{ "items": [...], "page": 1, "size": 20, "total": 134 }`.
 *
 * @template T
 */
final readonly class Page
{
    /** @param list<T> $items */
    public function __construct(
        public array $items,
        public int $page,
        public int $size,
        public int $total,
    ) {}
}
