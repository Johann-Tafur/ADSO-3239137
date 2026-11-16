<?php

namespace Tests\Unit\Application;

use App\Application\Services\CreateMentorService;
use App\Domain\Errors\RoleNotAllowed;
use App\Domain\Errors\UsernameTaken;
use App\Domain\Errors\ValidationError;
use App\Domain\Role;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakePasswordHasher;
use Tests\Support\InMemoryUserRepository;

/** T-07 · HU-02: el admin da de alta mentores, contra fakes. */
final class CreateMentorServiceTest extends TestCase
{
    private InMemoryUserRepository $users;

    private FakePasswordHasher $hasher;

    private CreateMentorService $service;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository;
        $this->hasher = new FakePasswordHasher;
        $this->service = new CreateMentorService($this->users, $this->hasher);
    }

    public function test_creates_a_mentor_with_a_clean_username(): void
    {
        $user = $this->service->create('Laura ', 'clave-secreta', 'mentor');

        $this->assertSame('laura', $user->username());
        $this->assertSame(Role::Mentor, $user->role());
        $this->assertSame($user, $this->users->findByUsername('laura'));
    }

    public function test_only_the_hash_is_stored_never_the_password(): void
    {
        $user = $this->service->create('laura', 'clave-secreta', 'mentor');

        $this->assertNotSame('clave-secreta', $user->passwordHash());
        $this->assertTrue($this->hasher->verify('clave-secreta', $user->passwordHash()));
    }

    public function test_asking_for_admin_is_role_not_allowed(): void
    {
        try {
            $this->service->create('otro-admin', 'clave-secreta', 'admin');
            $this->fail('Debió rechazar el rol admin');
        } catch (RoleNotAllowed $error) {
            $this->assertSame('ROLE_NOT_ALLOWED', $error->errorCode());
        }
        $this->assertSame(0, $this->users->count());
    }

    public function test_an_unknown_role_is_a_validation_error(): void
    {
        $this->expectException(ValidationError::class);
        $this->service->create('laura', 'clave-secreta', 'root');
    }

    public function test_a_repeated_username_is_taken_even_with_other_case(): void
    {
        $this->service->create('laura', 'clave-secreta', 'mentor');

        try {
            $this->service->create(' LAURA', 'otra-clave', 'mentor');
            $this->fail('Debió rechazar el nombre repetido');
        } catch (UsernameTaken $error) {
            $this->assertSame('USERNAME_TAKEN', $error->errorCode());
        }
        $this->assertSame(1, $this->users->count());
    }

    public function test_an_empty_password_is_rejected(): void
    {
        try {
            $this->service->create('laura', '   ', 'mentor');
            $this->fail('Debió rechazar la clave vacía');
        } catch (ValidationError $error) {
            $this->assertSame('password', $error->details()[0]['field']);
        }
        $this->assertSame(0, $this->users->count());
    }
}
