<?php

namespace App\Domain;

use App\Domain\Errors\ValidationError;
use LogicException;

/**
 * Usuario que inicia sesión: admin o mentor (HU-01, HU-02).
 *
 * - El nombre de usuario se guarda en minúsculas y sin espacios a los lados (CA-02.2): "Laura " queda "laura".
 * - Guarda solo el hash de la clave, nunca la clave (RNF-3). El hash lo calcula el adaptador Argon2id (T-14).
 * - Que el nombre no se repita lo garantiza el índice único; el servicio responde USERNAME_TAKEN (T-07).
 * - Que la API no permita crear otro admin lo decide CreateMentorService con ROLE_NOT_ALLOWED (T-07);
 *   aquí solo se exige que el rol exista.
 */
final class User
{
    private const USERNAME_MAX = 120;

    private function __construct(
        private readonly string $id,
        private readonly string $username,
        private readonly string $passwordHash,
        private readonly Role $role,
    ) {}

    public static function create(string $id, string $username, string $passwordHash, Role $role): self
    {
        return new self($id, self::normalizeUsername($username), self::checkHash($passwordHash), $role);
    }

    /** Reconstruye un usuario leído de la base. Lo usa solo el repositorio. */
    public static function restore(string $id, string $username, string $passwordHash, Role $role): self
    {
        return new self($id, $username, self::checkHash($passwordHash), $role);
    }

    /**
     * Misma limpieza que al guardar. El login la usa para buscar: "Laura " encuentra a "laura".
     */
    public static function normalizeUsername(string $username): string
    {
        $username = mb_strtolower(trim($username));
        if ($username === '' || mb_strlen($username) > self::USERNAME_MAX) {
            throw ValidationError::forField(
                'username',
                'entre 1 y '.self::USERNAME_MAX.' caracteres',
                'El nombre de usuario es obligatorio y puede tener máximo '.self::USERNAME_MAX.' caracteres.',
            );
        }

        return $username;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    /** Solo para el repositorio y para verificar la clave. Ninguna respuesta HTTP lo incluye (RNF-3). */
    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function role(): Role
    {
        return $this->role;
    }

    /** Un hash vacío es un error de programación: nunca debe llegar aquí. */
    private static function checkHash(string $passwordHash): string
    {
        if ($passwordHash === '') {
            throw new LogicException('El usuario necesita el hash de su clave.');
        }

        return $passwordHash;
    }
}
