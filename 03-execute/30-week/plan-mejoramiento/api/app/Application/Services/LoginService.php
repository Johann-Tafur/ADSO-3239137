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

    public function login(string $username, string $password): AccessToken
    {
        try {
            $username = User::normalizeUsername($username);
        } catch (ValidationError) {
            throw InvalidCredentials::create();
        }

        $user = $this->users->findByUsername($username);
        if ($user === null || ! $this->hasher->verify($password, $user->passwordHash())) {
            throw InvalidCredentials::create();
        }

        return new AccessToken($this->tokens->issue($user), $this->tokens->lifetimeSeconds(), $user->role());
    }
}
