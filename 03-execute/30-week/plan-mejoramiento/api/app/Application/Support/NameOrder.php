<?php

namespace App\Application\Support;

use Collator;

/**
 * Orden alfabético en español, como lo espera una persona: sin distinguir mayúsculas y con los acentos en su lugar
 * («arduino», «Órbita», «Zumo»). Comparar bytes con strcmp() pondría «Zumo» antes que «arduino» y «Órbita» al final.
 * Necesita la extensión intl de PHP (composer.json la exige).
 */
final class NameOrder
{
    private static ?Collator $collator = null;

    public static function compare(string $a, string $b): int
    {
        self::$collator ??= new Collator('es');

        return self::$collator->compare($a, $b);
    }
}
