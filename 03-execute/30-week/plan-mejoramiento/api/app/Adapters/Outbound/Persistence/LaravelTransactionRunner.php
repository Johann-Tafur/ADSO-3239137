<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\TransactionRunner;
use Illuminate\Database\ConnectionInterface;

/**
 * TransactionRunner con la transacción de la conexión de Laravel: BEGIN, el bloque, COMMIT;
 * si el bloque lanza una excepción, ROLLBACK y la excepción sigue (CA-06.3).
 * Un solo intento: sin reintento automático (ADR-002).
 */
final class LaravelTransactionRunner implements TransactionRunner
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function run(callable $work): mixed
    {
        return $this->db->transaction(fn () => $work(), attempts: 1);
    }
}
