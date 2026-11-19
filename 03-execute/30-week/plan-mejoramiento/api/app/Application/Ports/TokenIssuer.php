<?php

namespace App\Application\Ports;

use App\Application\Results\AccessClaims;
use App\Domain\User;

/** Emite y verifica el token de sesión. El adaptador real firma un JWT HS256 con `sub` y `role` (plan D-11, T-14). */
interface TokenIssuer
{
    public function issue(User $user): string;

    /** Cuántos segundos dura el token: 8 horas = 28800 (CA-01.1). */
    public function lifetimeSeconds(): int;

    /** Las claims de un token válido y vigente; null si está mal formado, mal firmado o vencido (CA-01.3). */
    public function verify(string $token): ?AccessClaims;
}
