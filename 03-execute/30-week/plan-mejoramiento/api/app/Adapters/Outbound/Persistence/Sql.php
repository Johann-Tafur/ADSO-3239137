<?php

namespace App\Adapters\Outbound\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;

/** Utilidades que comparten los repositorios de PostgreSQL. */
final class Sql
{
    /**
     * Orden alfabético como lo espera una persona. La base usa por defecto el orden de bytes
     * («Zumo» antes que «arduino»); la colación ICU da «arduino», «Órbita», «Zumo», igual que NameOrder.
     */
    public const HUMAN_ORDER = 'COLLATE "und-x-icu"';

    /** Instante para guardar en una columna timestamptz, siempre en UTC y con microsegundos. */
    public static function toDb(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    /** Instante leído de una columna timestamptz, convertido a UTC. */
    public static function fromDb(?string $value): ?DateTimeImmutable
    {
        return $value === null ? null : (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
    }

    /** Texto para buscar con ILIKE '%…%': escapa %, _ y \ para que se busquen tal cual. */
    public static function contains(string $text): string
    {
        return '%'.addcslashes($text, '%_\\').'%';
    }

    /** ¿La excepción es una violación del índice único con ese nombre? (SQLSTATE 23505) */
    public static function violatesUnique(QueryException $error, string $index): bool
    {
        return $error->getCode() === '23505' && str_contains($error->getMessage(), $index);
    }
}
