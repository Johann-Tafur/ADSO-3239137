<?php

namespace App\Application\Ports;

/** Convierte una clave en hash y la verifica. El adaptador real usa Argon2id (D-09, T-14). */
interface PasswordHasher
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;
}
