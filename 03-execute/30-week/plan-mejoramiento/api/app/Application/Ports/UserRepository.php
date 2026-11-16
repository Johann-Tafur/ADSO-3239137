<?php

namespace App\Application\Ports;

use App\Domain\User;

/** Guarda y lee usuarios. El adaptador real (T-12) usa la tabla robotics.user. */
interface UserRepository
{
    /** Id nuevo (UUID) para un usuario que todavía no existe. */
    public function nextId(): string;

    /** Busca por nombre ya normalizado (minúsculas y recortado). */
    public function findByUsername(string $username): ?User;

    /**
     * Guarda un usuario nuevo.
     *
     * @throws \App\Domain\Errors\UsernameTaken si otro guardó el mismo nombre primero (índice único)
     */
    public function add(User $user): void;
}
