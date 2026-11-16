<?php

namespace Tests\Support;

use App\Application\Ports\TokenIssuer;
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
}
