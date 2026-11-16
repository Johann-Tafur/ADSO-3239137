<?php

namespace App\Application\Ports;

/**
 * Corre un bloque como una sola transacción de la base: o se guarda todo, o nada (CA-06.3, ADR-002).
 * Si el bloque lanza una excepción, se deshace todo y la excepción sigue su camino.
 */
interface TransactionRunner
{
    /**
     * @template T
     *
     * @param callable(): T $work
     * @return T
     */
    public function run(callable $work): mixed;
}
