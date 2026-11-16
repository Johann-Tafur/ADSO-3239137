<?php

namespace App\Domain\Errors;

/** Por la API solo se crean mentores; pedir otro rol responde ROLE_NOT_ALLOWED (422, CA-02.3). */
final class RoleNotAllowed extends DomainError
{
    public static function role(string $role): self
    {
        return new self(
            "Por la API solo se crean usuarios con rol mentor, no {$role}.",
            [['field' => 'role', 'issue' => 'solo mentor']],
        );
    }

    public function errorCode(): string
    {
        return 'ROLE_NOT_ALLOWED';
    }
}
