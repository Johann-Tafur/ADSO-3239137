<?php

namespace App\Adapters\Outbound\Persistence;

use Illuminate\Support\Str;

/**
 * Identificadores UUID v4 (api-contract.md §1).
 * Las columnas id son de tipo uuid: buscar con un texto que no es UUID haría fallar la consulta en PostgreSQL
 * (error 22P02). Por eso los repositorios responden «no existe» sin preguntarle a la base.
 */
final class Uuid
{
    public static function new(): string
    {
        return Str::uuid()->toString();
    }

    public static function isValid(string $value): bool
    {
        return Str::isUuid($value);
    }
}
