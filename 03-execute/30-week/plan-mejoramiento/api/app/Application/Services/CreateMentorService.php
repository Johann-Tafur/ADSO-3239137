<?php

namespace App\Application\Services;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Errors\RoleNotAllowed;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Errors\ValidationError;
use App\Domain\Role;
use App\Domain\User;

/**
 * HU-02 · El admin da de alta mentores.
 *
 * - Solo crea rol mentor; pedir admin → ROLE_NOT_ALLOWED (CA-02.3). El admin inicial nace del entorno (T-17).
 * - Nombre repetido → USERNAME_TAKEN (CA-02.2). Se revisa antes de guardar, y si dos piden el mismo nombre a la vez,
 *   el índice único uq_user_username frena al segundo y el repositorio responde el mismo error.
 * - La clave solo se usa para calcular el hash; no se guarda ni se devuelve (RNF-3).
 *
 * Que quien llama sea admin lo revisa la capa HTTP (T-16).
 */
final class CreateMentorService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {}

    public function create(string $username, string $password, string $role): User
    {
        // 1. Validar la entrada
        $role = Role::fromName($role);
        if ($role !== Role::Mentor) {
            throw RoleNotAllowed::role($role->value);
        }
        if (trim($password) === '') {
            throw ValidationError::forField('password', 'obligatoria', 'La clave es obligatoria.');
        }
        $username = User::normalizeUsername($username);

        // 2. Reglas que miran la base
        if ($this->users->findByUsername($username) !== null) {
            throw UsernameTaken::named($username);
        }

        // 3. Crear y guardar
        $user = User::create($this->users->nextId(), $username, $this->hasher->hash($password), $role);
        $this->users->add($user);

        return $user;
    }
}
