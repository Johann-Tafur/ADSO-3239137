<?php

namespace Tests\Support;

use App\Application\Ports\TokenIssuer;
use App\Application\Results\AccessClaims;
use App\Domain\Role;
use App\Domain\User;

/** Fake de TokenIssuer: un texto legible con el id y el rol, en lugar de un JWT firmado. */
final class FakeTokenIssuer implements TokenIssuer
{
    public function issue(User $user): string
    {
        return "token-for:{$user->id()}:{$user->role()->value}";
    }

    public function lifetimeSeconds(): int
    {
        return 8 * 3600;
    }

    public function verify(string $token): ?AccessClaims
    {
        $parts = explode(':', $token);
        if (count($parts) !== 3 || $parts[0] !== 'token-for' || Role::tryFrom($parts[2]) === null) {
            return null;
        }

        return new AccessClaims($parts[1], Role::from($parts[2]));
    }
}
