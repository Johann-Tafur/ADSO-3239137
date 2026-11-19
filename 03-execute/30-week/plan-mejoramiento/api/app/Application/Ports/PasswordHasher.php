<?php

namespace App\Application\Ports;

/**
 * Convierte una clave en hash y la verifica. El adaptador real usa Argon2id (D-09, T-14).
 * #[\SensitiveParameter]: si algo falla, PHP no muestra la clave en la traza del error ni en los logs (RNF-3).
 */
interface PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $password): string;

    public function verify(#[\SensitiveParameter] string $password, string $hash): bool;
}
