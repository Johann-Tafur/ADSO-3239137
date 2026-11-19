<?php

namespace Tests\Feature;

use App\Application\Ports\UserRepository;
use App\Domain\Role;
use App\Domain\User;

/** T-17 · Con la base vacía, tras migrate y robotics:ensure-admin, se inicia sesión como admin (plan D-10). */
final class EnsureAdminTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['robotics.admin_username' => 'Admin ', 'robotics.admin_password' => 'clave-del-entorno']);
    }

    public function test_after_ensure_admin_the_admin_can_log_in(): void
    {
        $this->artisan('robotics:ensure-admin')->expectsOutput('Admin creado.')->assertSuccessful();

        $this->postJson(self::API.'/auth/login', ['username' => 'admin', 'password' => 'clave-del-entorno'])
            ->assertOk()->assertJsonPath('role', 'admin');
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->artisan('robotics:ensure-admin')->assertSuccessful();
        $hash = $this->app->make(UserRepository::class)->findByUsername('admin')->passwordHash();

        config(['robotics.admin_password' => 'otra-clave']);
        $this->artisan('robotics:ensure-admin')->expectsOutput('El admin ya existía; no se cambió nada.')->assertSuccessful();

        $this->assertSame($hash, $this->app->make(UserRepository::class)->findByUsername('admin')->passwordHash());
    }

    public function test_without_environment_values_it_fails_and_creates_nobody(): void
    {
        config(['robotics.admin_password' => '']);

        $this->artisan('robotics:ensure-admin')->assertFailed();
        $this->assertNull($this->app->make(UserRepository::class)->findByUsername('admin'));
    }

    public function test_it_never_turns_an_existing_mentor_into_admin(): void
    {
        $users = $this->app->make(UserRepository::class);
        $users->add(User::create($users->nextId(), 'admin', 'hash', Role::Mentor));

        $this->artisan('robotics:ensure-admin')->assertFailed();
        $this->assertSame(Role::Mentor, $users->findByUsername('admin')->role());
    }

    public function test_the_password_is_never_printed(): void
    {
        $this->artisan('robotics:ensure-admin')
            ->doesntExpectOutputToContain('clave-del-entorno')
            ->assertSuccessful();
    }
}
