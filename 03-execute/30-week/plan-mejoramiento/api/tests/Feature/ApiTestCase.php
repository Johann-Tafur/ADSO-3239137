<?php

namespace Tests\Feature;

use App\Application\Ports\Clock;
use App\Application\Ports\PasswordHasher;
use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\Integration\MigratesOnce;
use Tests\Support\FixedClock;
use Tests\TestCase;

/**
 * Base de las pruebas HTTP de T-16: peticiones de verdad contra las rutas, con los adaptadores REALES
 * (PostgreSQL en robotics_test, Argon2id, JWT, disco). Solo el reloj es fijo, para poder adelantar días.
 */
abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;
    use MigratesOnce;

    protected const API = '/api/v1';

    protected const INICIACION = '11111111-1111-4111-8111-111111111111';

    protected const SENSORES = '22222222-2222-4222-8222-222222222222';

    protected FixedClock $clock;

    protected string $photosDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = new FixedClock('2026-11-18T15:00:00Z');
        $this->app->instance(Clock::class, $this->clock);

        $this->photosDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'robotics-api-photos-'.bin2hex(random_bytes(4));
        config(['robotics.photos_dir' => $this->photosDir]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->photosDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->photosDir)) {
            rmdir($this->photosDir);
        }
        parent::tearDown();
    }

    /** Crea el usuario directamente en la base (como hará robotics:ensure-admin) y devuelve su token. */
    protected function tokenFor(string $username, Role $role, string $password = 'clave-de-prueba'): string
    {
        $users = $this->app->make(UserRepository::class);
        if ($users->findByUsername($username) === null) {
            $users->add(User::create($users->nextId(), $username, $this->app->make(PasswordHasher::class)->hash($password), $role));
        }

        return $this->postJson(self::API.'/auth/login', ['username' => $username, 'password' => $password])->json('access_token');
    }

    protected function admin(): string
    {
        return $this->tokenFor('admin', Role::Admin);
    }

    protected function mentor(): string
    {
        return $this->tokenFor('laura', Role::Mentor);
    }

    /** @param array<string, mixed> $body */
    protected function api(string $method, string $uri, string $token, array $body = []): TestResponse
    {
        return $this->withHeader('Authorization', "Bearer {$token}")->json($method, self::API.$uri, $body);
    }

    /** El cuerpo de error único: { code, message, details } (RNF-4). */
    protected function assertError(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)->assertJsonPath('code', $code)->assertJsonStructure(['code', 'message', 'details']);
        $this->assertSame(['code', 'message', 'details'], array_keys($response->json()));
    }

    protected function createKit(string $token, string $reference, int $units = 2, string $category = self::INICIACION, string $name = ''): string
    {
        return $this->api('POST', '/kits', $token, [
            'name' => $name !== '' ? $name : "Kit {$reference}",
            'manufacturer' => 'Fabricante',
            'reference' => $reference,
            'category_id' => $category,
            'total_units' => $units,
        ])->assertCreated()->json('id');
    }

    protected function createParticipant(string $token, string $name, string $document): string
    {
        return $this->api('POST', '/participants', $token, ['full_name' => $name, 'document_number' => $document])
            ->assertCreated()->json('id');
    }
}
