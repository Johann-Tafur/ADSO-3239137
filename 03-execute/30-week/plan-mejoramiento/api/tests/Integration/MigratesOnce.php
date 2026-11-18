<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;

/**
 * Corre las migraciones en robotics_test la primera vez en el proceso; después no hace nada («Nothing to migrate»).
 * Va en setUpTraits() para que ocurra antes de que DatabaseTransactions abra la transacción de la prueba.
 */
trait MigratesOnce
{
    private static bool $migrated = false;

    protected function setUpTraits()
    {
        if (! self::$migrated) {
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }

        return parent::setUpTraits();
    }
}
