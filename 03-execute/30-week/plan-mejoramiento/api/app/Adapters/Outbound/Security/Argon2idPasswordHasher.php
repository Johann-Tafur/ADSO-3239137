<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\PasswordHasher;

/**
 * PasswordHasher con Argon2id, el algoritmo recomendado para claves (plan D-09, artículo IX).
 * Cada hash lleva su propia sal aleatoria: la misma clave da hashes distintos.
 * Se usan los parámetros por defecto de PHP (64 MB de memoria, 4 pasadas), pensados para que adivinar sea caro.
 */
final class Argon2idPasswordHasher implements PasswordHasher
{
    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /** Comparación en tiempo constante dentro de password_verify(). Un hash que no es Argon2id simplemente no verifica. */
    public function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
