<?php

namespace App\Application\Paging;

use App\Domain\Errors\ValidationError;

/** Qué página se pide: `page` desde 1 y `size` de 1 a 50 (api-contract.md §1, CA-04.1). */
final readonly class PageRequest
{
    public const DEFAULT_SIZE = 20;

    public const MAX_SIZE = 50;

    public function __construct(
        public int $page = 1,
        public int $size = self::DEFAULT_SIZE,
    ) {
        if ($page < 1) {
            throw ValidationError::forField('page', 'mayor o igual a 1', 'La página empieza en 1.');
        }
        if ($size < 1 || $size > self::MAX_SIZE) {
            throw ValidationError::forField('size', 'entre 1 y '.self::MAX_SIZE, 'El tamaño de página va de 1 a '.self::MAX_SIZE.'.');
        }
    }

    /** Cuántos resultados se saltan antes de esta página. */
    public function offset(): int
    {
        return ($this->page - 1) * $this->size;
    }
}
