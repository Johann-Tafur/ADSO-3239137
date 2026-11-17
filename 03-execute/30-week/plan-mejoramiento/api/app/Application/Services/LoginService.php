<?php

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Application\Results\AccessToken;
use App\Domain\Errors\InvalidCredentials;
use App\Domain\Errors\ValidationError;
use App\Domain\User;

/**
 * HU-01 · Iniciar sesión.
 *
 * Usuario inexistente, usuario vacío o clave equivocada responden lo mismo: INVALID_CREDENTIALS (CA-01.2).
 * Así nadie puede averiguar qué nombres de usuario existen.
 */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {}

    /** Hash de relleno para verificar aunque el usuario no exista; se calcula una sola vez. */
    private ?string $decoyHash = null;

    public function login(string $username, string $password): AccessToken
    {
        try {
            $user = $this->users->findByUsername(User::normalizeUsername($username));
        } catch (ValidationError) {
            $user = null;
        }

        // Siempre se verifica una clave, exista o no el usuario: así la respuesta tarda lo mismo
        // y nadie puede medir el tiempo para saber qué usuarios existen (CA-01.2).
        $hash = $user?->passwordHash() ?? $this->decoyHash();
        if (! $this->hasher->verify($password, $hash) || $user === null) {
            throw InvalidCredentials::create();
        }

        return new AccessToken($this->tokens->issue($user), $this->tokens->lifetimeSeconds(), $user->role());
    }

    private function decoyHash(): string
    {
        return $this->decoyHash ??= $this->hasher->hash(bin2hex(random_bytes(16)));
    }
}
