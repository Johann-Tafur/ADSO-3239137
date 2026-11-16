<?php

namespace Tests\Support;

use App\Application\Ports\PasswordHasher;

/** Fake de PasswordHasher: no cifra de verdad, pero el hash nunca es igual a la clave. */
final class FakePasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return 'hashed:'.strrev($password);
    }

    public function verify(string $password, string $hash): bool
    {
        return $this->hash($password) === $hash;
    }
}
