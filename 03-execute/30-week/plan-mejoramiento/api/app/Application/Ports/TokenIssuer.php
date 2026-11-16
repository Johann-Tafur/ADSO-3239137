<?php

namespace App\Application\Ports;

use App\Domain\User;

/** Emite el token de sesión. El adaptador real firma un JWT con `sub` y `role` (T-14). */
interface TokenIssuer
{
    public function issue(User $user): string;

    /** Cuántos segundos dura el token: 8 horas = 28800 (CA-01.1). */
    public function lifetimeSeconds(): int;
}
