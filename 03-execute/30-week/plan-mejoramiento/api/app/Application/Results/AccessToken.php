<?php

namespace App\Application\Results;

use App\Domain\Role;

/** Lo que devuelve un login correcto. El controlador lo convierte en el JSON de api-contract.md §3.1. */
final readonly class AccessToken
{
    public string $tokenType;

    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public Role $role,
    ) {
        $this->tokenType = 'Bearer';
    }
}
