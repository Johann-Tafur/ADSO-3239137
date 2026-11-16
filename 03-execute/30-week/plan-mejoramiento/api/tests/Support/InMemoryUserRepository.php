<?php

namespace Tests\Support;

use App\Application\Ports\UserRepository;
use App\Domain\Errors\UsernameTaken;
use App\Domain\User;

/** Fake de UserRepository: guarda en un arreglo. Imita el índice único de username. */
final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> por username */
    private array $users = [];

    private int $sequence = 0;

    public function nextId(): string
    {
        return 'user-'.++$this->sequence;
    }

    public function findByUsername(string $username): ?User
    {
        return $this->users[$username] ?? null;
    }

    public function add(User $user): void
    {
        if (isset($this->users[$user->username()])) {
            throw UsernameTaken::named($user->username());
        }
        $this->users[$user->username()] = $user;
    }

    public function count(): int
    {
        return count($this->users);
    }
}
