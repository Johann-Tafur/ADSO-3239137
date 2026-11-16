<?php

namespace App\Domain\Errors;

/** Ya existe un usuario con ese nombre (USERNAME_TAKEN, 409, CA-02.2). */
final class UsernameTaken extends DomainError
{
    public static function named(string $username): self
    {
        return new self(
            "El nombre de usuario «{$username}» ya existe.",
            [['field' => 'username', 'issue' => 'repetido']],
        );
    }

    public function errorCode(): string
    {
        return 'USERNAME_TAKEN';
    }
}
