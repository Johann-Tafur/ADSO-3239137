<?php

namespace Tests\Support;

use App\Application\Ports\PasswordHasher;

/** Fake de PasswordHasher: no cifra de verdad, pero el hash nunca es igual a la clave. */
final class FakePasswordHasher implements PasswordHasher
{
    /** Cuántas veces se verificó una clave (para comprobar que el login siempre verifica). */
    public int $verifications = 0;

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return 'hashed:'.strrev($password);
    }

    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        $this->verifications++;

        return $this->hash($password) === $hash;
    }
}
