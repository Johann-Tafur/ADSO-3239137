<?php

namespace Tests\Feature;

use App\Domain\Role;

/** T-16 · HU-01, HU-02 y CA-01.3 por HTTP: login, mentores, 401 y 403, y que la clave nunca sale (RNF-3). */
final class AuthApiTest extends ApiTestCase
{
    public function test_login_returns_a_bearer_token_for_8_hours_with_the_role(): void
    {
        $this->tokenFor('laura', Role::Mentor, 'clave-laura');

        $this->postJson(self::API.'/auth/login', ['username' => ' LAURA ', 'password' => 'clave-laura'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 28800)
            ->assertJsonPath('role', 'mentor')
            ->assertJsonStructure(['access_token']);
    }

    public function test_bad_credentials_are_401_without_saying_which_part_failed(): void
    {
        $this->tokenFor('laura', Role::Mentor, 'clave-laura');

        $wrongPassword = $this->postJson(self::API.'/auth/login', ['username' => 'laura', 'password' => 'otra']);
        $unknownUser = $this->postJson(self::API.'/auth/login', ['username' => 'pedro', 'password' => 'clave-laura']);

        $this->assertError($wrongPassword, 401, 'INVALID_CREDENTIALS');
        $this->assertError($unknownUser, 401, 'INVALID_CREDENTIALS');
        $this->assertSame($wrongPassword->json('message'), $unknownUser->json('message'));
    }

    public function test_without_token_or_with_a_bad_or_expired_token_it_is_401(): void
    {
        $token = $this->mentor();

        $this->assertError($this->getJson(self::API.'/kits'), 401, 'UNAUTHENTICATED');
        $this->assertError($this->api('GET', '/kits', 'token-inventado'), 401, 'UNAUTHENTICATED');

        $this->clock->advance('+8 hours 1 minute');
        $this->assertError($this->api('GET', '/kits', $token), 401, 'UNAUTHENTICATED');
    }

    public function test_health_and_login_are_public(): void
    {
        $this->getJson(self::API.'/health')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_the_admin_creates_a_mentor_and_the_answer_has_no_password(): void
    {
        $response = $this->api('POST', '/users', $this->admin(), ['username' => 'Pedro ', 'password' => 'secreta-123', 'role' => 'mentor']);

        $response->assertCreated()->assertJsonPath('username', 'pedro')->assertJsonPath('role', 'mentor');
        $this->assertSame(['id', 'username', 'role'], array_keys($response->json()));
        $this->assertStringNotContainsString('secreta-123', $response->getContent());
        $this->assertStringNotContainsString('argon2id', $response->getContent());
    }

    public function test_asking_for_the_admin_role_is_422_role_not_allowed(): void
    {
        $this->assertError(
            $this->api('POST', '/users', $this->admin(), ['username' => 'jefe', 'password' => 'x', 'role' => 'admin']),
            422,
            'ROLE_NOT_ALLOWED',
        );
    }

    public function test_a_repeated_username_is_409(): void
    {
        $admin = $this->admin();
        $this->api('POST', '/users', $admin, ['username' => 'pedro', 'password' => 'x', 'role' => 'mentor'])->assertCreated();

        $this->assertError($this->api('POST', '/users', $admin, ['username' => 'PEDRO', 'password' => 'y', 'role' => 'mentor']), 409, 'USERNAME_TAKEN');
    }

    public function test_a_mentor_cannot_use_admin_routes_and_gets_403(): void
    {
        $mentor = $this->mentor();

        foreach ([
            ['POST', '/users'],
            ['POST', '/kits'],
            ['GET', '/reports/most-requested?from=2026-11-01&to=2026-11-30'],
        ] as [$method, $uri]) {
            $this->assertError($this->api($method, $uri, $mentor), 403, 'FORBIDDEN_ROLE');
        }
    }

    public function test_403_does_not_reveal_whether_the_kit_exists(): void
    {
        $mentor = $this->mentor();

        $existing = $this->api('DELETE', '/kits/'.$this->createKit($this->admin(), 'ARD-0001'), $mentor);
        $missing = $this->api('DELETE', '/kits/00000000-0000-4000-8000-000000000000', $mentor);

        $this->assertError($existing, 403, 'FORBIDDEN_ROLE');
        $this->assertError($missing, 403, 'FORBIDDEN_ROLE');
    }

    public function test_missing_fields_are_422_with_the_field_in_details(): void
    {
        $response = $this->postJson(self::API.'/auth/login', ['username' => 'laura']);

        $this->assertError($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('details.0.field', 'password')->assertJsonPath('details.0.issue', 'obligatorio');
    }
}
