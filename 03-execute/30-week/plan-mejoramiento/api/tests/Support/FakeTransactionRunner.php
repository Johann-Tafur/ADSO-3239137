<?php

namespace Tests\Support;

use App\Application\Ports\TransactionRunner;
use Throwable;

/**
 * Fake de TransactionRunner que se porta como una transacción: si el bloque lanza una excepción,
 * deja los fakes como estaban antes de empezar (rollback) y deja pasar la excepción.
 */
final class FakeTransactionRunner implements TransactionRunner
{
    /** @var list<Snapshottable> */
    private array $stores;

    public int $runs = 0;

    public int $rollbacks = 0;

    public function __construct(Snapshottable ...$stores)
    {
        $this->stores = $stores;
    }

    public function run(callable $work): mixed
    {
        $this->runs++;
        $snapshots = array_map(fn (Snapshottable $store) => $store->snapshot(), $this->stores);

        try {
            return $work();
        } catch (Throwable $error) {
            foreach ($this->stores as $i => $store) {
                $store->restoreSnapshot($snapshots[$i]);
            }
            $this->rollbacks++;
            throw $error;
        }
    }
}
