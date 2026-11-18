<?php

namespace App\Adapters\Outbound\Persistence;

use App\Application\Ports\UserRepository;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Role;
use App\Domain\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;

/**
 * UserRepository sobre robotics."user" (entre comillas: user es palabra reservada en PostgreSQL).
 * password_hash solo se lee para verificar la clave; nunca se registra en logs (RNF-3).
 */
final class PostgresUserRepository implements UserRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function nextId(): string
    {
        return Uuid::new();
    }

    public function findByUsername(string $username): ?User
    {
        $row = $this->db->selectOne('SELECT id, username, password_hash, role FROM "user" WHERE username = ?', [$username]);

        return $row === null ? null : User::restore($row->id, $row->username, $row->password_hash, Role::from($row->role));
    }

    public function add(User $user): void
    {
        try {
            $this->db->insert(
                'INSERT INTO "user" (id, username, password_hash, role) VALUES (?, ?, ?, ?)',
                [$user->id(), $user->username(), $user->passwordHash(), $user->role()->value],
            );
        } catch (QueryException $error) {
            if (Sql::violatesUnique($error, 'uq_user_username')) {
                throw UsernameTaken::named($user->username());
            }
            // El mensaje de QueryException trae los valores del INSERT, incluido el hash: no se deja salir (RNF-3)
            throw new \RuntimeException('No se pudo guardar el usuario (SQLSTATE '.$error->getCode().').');
        }
    }
}
