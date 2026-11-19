<?php

namespace Tests\Unit\Adapters;

use App\Adapters\Outbound\Security\Argon2idPasswordHasher;
use App\Application\Ports\PasswordHasher;
use App\Application\Services\LoginService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeTokenIssuer;
use Tests\Support\InMemoryUserRepository;

/** T-14 · Argon2id (plan D-09) y RNF-3: la clave no se guarda ni aparece en una traza de error. */
final class Argon2idPasswordHasherTest extends TestCase
{
    private const PASSWORD = 'Clave-Secreta-2026';

    public function test_the_hash_is_argon2id_and_never_contains_the_password(): void
    {
        $hash = (new Argon2idPasswordHasher)->hash(self::PASSWORD);

        $this->assertStringStartsWith('$argon2id$', $hash);
        $this->assertStringNotContainsString(self::PASSWORD, $hash);
    }

    public function test_it_verifies_the_right_password_and_rejects_any_other(): void
    {
        $hasher = new Argon2idPasswordHasher;
        $hash = $hasher->hash(self::PASSWORD);

        $this->assertTrue($hasher->verify(self::PASSWORD, $hash));
        $this->assertFalse($hasher->verify('clave-secreta-2026', $hash), 'distingue mayúsculas');
        $this->assertFalse($hasher->verify('', $hash));
    }

    public function test_the_same_password_gives_a_different_hash_each_time(): void
    {
        $hasher = new Argon2idPasswordHasher;

        $this->assertNotSame($hasher->hash(self::PASSWORD), $hasher->hash(self::PASSWORD), 'cada hash lleva su propia sal');
    }

    public function test_a_hash_that_is_not_argon2id_does_not_verify(): void
    {
        $this->assertFalse((new Argon2idPasswordHasher)->verify(self::PASSWORD, 'texto-cualquiera'));
    }

    public function test_if_something_fails_during_login_the_password_is_not_in_the_error_trace(): void
    {
        $exploding = new class implements PasswordHasher
        {
            public function hash(#[\SensitiveParameter] string $password): string
            {
                return 'x';
            }

            public function verify(#[\SensitiveParameter] string $password, string $hash): bool
            {
                throw new RuntimeException('falla simulada del hasher');
            }
        };
        $login = new LoginService(new InMemoryUserRepository, $exploding, new FakeTokenIssuer);

        try {
            $login->login('laura', self::PASSWORD);
            $this->fail('Debió fallar');
        } catch (RuntimeException $error) {
            // La traza es lo que Laravel escribe en storage/logs cuando hay un error 500
            $this->assertStringNotContainsString(self::PASSWORD, $error->getTraceAsString());
            $this->assertStringNotContainsString(self::PASSWORD, print_r($error->getTrace(), true));
        }
    }
}
