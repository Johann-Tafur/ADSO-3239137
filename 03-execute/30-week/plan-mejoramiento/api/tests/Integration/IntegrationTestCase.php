<?php

namespace Tests\Integration;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Base de las pruebas contra PostgreSQL real, en la base robotics_test (phpunit.xml, infra/README.md).
 *
 * Cada prueba corre dentro de una transacción que se deshace al terminar (DatabaseTransactions):
 * la base queda vacía para la siguiente y nunca se mezclan datos entre pruebas.
 * Las pruebas con dos conexiones a la vez no pueden usar esto (no verían los datos de la otra): ver ConcurrencyTest.
 */
abstract class IntegrationTestCase extends TestCase
{
    use DatabaseTransactions;
    use MigratesOnce;

    protected function db(): ConnectionInterface
    {
        return DB::connection();
    }
}
