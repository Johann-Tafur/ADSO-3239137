<?php

namespace Tests\Integration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base de las pruebas contra PostgreSQL real, en la base robotics_test (phpunit.xml, infra/README.md).
 *
 * - La primera prueba del proceso corre las migraciones; si ya están, no hace nada («Nothing to migrate»).
 * - Cada prueba corre dentro de una transacción que se deshace al terminar (DatabaseTransactions):
 *   la base queda vacía para la siguiente y nunca se mezclan datos entre pruebas.
 */
abstract class IntegrationTestCase extends TestCase
{
    use DatabaseTransactions;

    private static bool $migrated = false;

    /** Migrar antes de que DatabaseTransactions abra la transacción de la prueba. */
    protected function setUpTraits()
    {
        if (! self::$migrated) {
            Artisan::call('migrate', ['--force' => true]);
            self::$migrated = true;
        }

        return parent::setUpTraits();
    }

    protected function db(): ConnectionInterface
    {
        return DB::connection();
    }
}
