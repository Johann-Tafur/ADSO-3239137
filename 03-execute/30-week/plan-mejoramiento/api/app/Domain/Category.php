<?php

namespace App\Domain;

/** Categoría de kit. Son cinco, fijas, sembradas por la migración (spec.md §2): no se crean ni se editan. */
final readonly class Category
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
