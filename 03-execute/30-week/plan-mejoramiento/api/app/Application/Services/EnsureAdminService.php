<?php

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Errors\ValidationError;
use App\Domain\Role;
use App\Domain\User;
use LogicException;

/**
 * T-17 · Crea el admin inicial con los datos del entorno (plan D-10). Es la ÚNICA forma de que exista un admin:
 * la API solo crea mentores (CA-02.3) y la base no puede calcular un hash Argon2id.
 *
 * Se puede correr en cada arranque: si el admin ya existe no hace nada (no cambia su clave).
 */
final class EnsureAdminService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {}

    /** @return bool true si lo creó; false si ya existía */
    public function ensure(string $username, #[\SensitiveParameter] string $password): bool
    {
        $username = User::normalizeUsername($username);
        if (trim($password) === '') {
            throw ValidationError::forField('ADMIN_PASSWORD', 'obligatoria', 'Falta ADMIN_PASSWORD en el entorno.');
        }

        $existing = $this->users->findByUsername($username);
        if ($existing !== null) {
            if (! $existing->isAdmin()) {
                // Nunca se convierte un mentor en admin en silencio
                throw new LogicException("El usuario «{$username}» ya existe y no es admin.");
            }

            return false;
        }

        $this->users->add(User::create($this->users->nextId(), $username, $this->hasher->hash($password), Role::Admin));

        return true;
    }
}
