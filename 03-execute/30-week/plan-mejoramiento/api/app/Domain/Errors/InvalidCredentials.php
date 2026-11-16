<?php

namespace App\Domain\Errors;

/**
 * Usuario o clave incorrectos (INVALID_CREDENTIALS, 401, CA-01.2).
 * El mensaje es siempre el mismo: no dice cuál de los dos falló.
 */
final class InvalidCredentials extends DomainError
{
    public static function create(): self
    {
        return new self('Usuario o clave incorrectos.');
    }

    public function errorCode(): string
    {
        return 'INVALID_CREDENTIALS';
    }
}
