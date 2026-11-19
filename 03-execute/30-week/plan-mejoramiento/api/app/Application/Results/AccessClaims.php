<?php

namespace App\Application\Results;

use App\Domain\Role;

/** Lo que trae un token válido: quién es (`sub`) y con qué rol entra (`role`). Sin datos personales (api-contract.md §4). */
final readonly class AccessClaims
{
    public function __construct(
        public string $userId,
        public Role $role,
    ) {}
}
