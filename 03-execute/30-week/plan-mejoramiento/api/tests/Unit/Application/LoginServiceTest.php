<?php

namespace Tests\Unit\Application;

use App\Application\Services\LoginService;
use App\Domain\Errors\InvalidCredentials;
use App\Domain\Role;
use App\Domain\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\FakeTokenIssuer;
use Tests\Support\InMemoryUserRepository;

/** T-07 · HU-01: iniciar sesión, contra fakes. */
final class LoginServiceTest extends TestCase
{
    private LoginService $service;

    private FakePasswordHasher $hasher;

    protected function setUp(): void
    {
        $users = new InMemoryUserRepository;
        $this->hasher = new FakePasswordHasher;
        $users->add(User::create('u-1', 'laura', $this->hasher->hash('clave-secreta'), Role::Mentor));

        $this->service = new LoginService($users, $this->hasher, new FakeTokenIssuer);
    }

    public function test_valid_credentials_return_a_bearer_token_for_8_hours(): void
    {
        $token = $this->service->login('laura', 'clave-secreta');

        $this->assertSame('token-for:u-1:mentor', $token->accessToken);
        $this->assertSame('Bearer', $token->tokenType);
        $this->assertSame(28800, $token->expiresIn);
        $this->assertSame(Role::Mentor, $token->role);
    }

    public function test_the_username_is_found_however_it_is_typed(): void
    {
        $token = $this->service->login('  LAURA ', 'clave-secreta');

        $this->assertSame(Role::Mentor, $token->role);
    }

    /** @return array<string, array{string, string}> */
    public static function badCredentials(): array
    {
        return [
            'clave equivocada' => ['laura', 'otra-clave'],
            'usuario inexistente' => ['pedro', 'clave-secreta'],
            'usuario vacío' => ['   ', 'clave-secreta'],
            'clave vacía' => ['laura', ''],
        ];
    }

    #[DataProvider('badCredentials')]
    public function test_bad_credentials_always_give_the_same_answer(string $username, string $password): void
    {
        try {
            $this->service->login($username, $password);
            $this->fail('Debió rechazar las credenciales');
        } catch (InvalidCredentials $error) {
            // CA-01.2: el mismo código y el mismo mensaje, sin decir qué falló
            $this->assertSame('INVALID_CREDENTIALS', $error->errorCode());
            $this->assertSame('Usuario o clave incorrectos.', $error->getMessage());
        }
        // Exista o no el usuario, se verifica una clave: el tiempo de respuesta no lo delata
        $this->assertSame(1, $this->hasher->verifications);
    }
}
