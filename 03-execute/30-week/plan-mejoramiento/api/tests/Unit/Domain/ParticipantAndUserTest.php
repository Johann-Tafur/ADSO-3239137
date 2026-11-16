<?php

namespace Tests\Unit\Domain;

use App\Domain\Errors\ValidationError;
use App\Domain\Participant;
use App\Domain\Role;
use App\Domain\User;
use App\Domain\ValueObjects\DocumentNumber;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** T-06: participante y usuario. */
final class ParticipantAndUserTest extends TestCase
{
    // ---------- Participante (HU-05) ----------

    public function test_a_participant_keeps_its_name_without_outer_spaces(): void
    {
        $participant = Participant::create('p1', '  Ana Gómez ', new DocumentNumber(' 1020304050 '));

        $this->assertSame('Ana Gómez', $participant->fullName());
        $this->assertSame('1020304050', $participant->documentNumber()->value);
    }

    public function test_a_participant_without_name_is_rejected(): void
    {
        try {
            Participant::create('p1', '   ', new DocumentNumber('123'));
            $this->fail('Debió rechazar el nombre vacío');
        } catch (ValidationError $error) {
            $this->assertSame('full_name', $error->details()[0]['field']);
        }
    }

    public function test_a_participant_name_longer_than_200_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        Participant::create('p1', str_repeat('a', 201), new DocumentNumber('123'));
    }

    // ---------- Usuario (HU-01, HU-02) ----------

    public function test_the_username_is_stored_in_lowercase_and_trimmed(): void
    {
        $user = User::create('u1', 'Laura ', 'hash', Role::Mentor);

        $this->assertSame('laura', $user->username());
    }

    public function test_login_finds_the_same_username_however_it_is_typed(): void
    {
        $this->assertSame('laura', User::normalizeUsername('  LAURA'));
    }

    public function test_an_empty_username_is_rejected(): void
    {
        try {
            User::create('u1', '  ', 'hash', Role::Mentor);
            $this->fail('Debió rechazar el usuario vacío');
        } catch (ValidationError $error) {
            $this->assertSame('username', $error->details()[0]['field']);
        }
    }

    public function test_a_username_longer_than_120_is_rejected(): void
    {
        $this->expectException(ValidationError::class);
        User::create('u1', str_repeat('a', 121), 'hash', Role::Mentor);
    }

    public function test_a_user_without_password_hash_is_a_programming_error(): void
    {
        $this->expectException(LogicException::class);
        User::create('u1', 'laura', '', Role::Mentor);
    }

    public function test_only_admin_is_admin(): void
    {
        $this->assertTrue(User::create('u1', 'root', 'hash', Role::Admin)->isAdmin());
        $this->assertFalse(User::create('u2', 'laura', 'hash', Role::Mentor)->isAdmin());
    }

    // ---------- Rol ----------

    public function test_admin_and_mentor_are_accepted(): void
    {
        $this->assertSame(Role::Admin, Role::fromName('admin'));
        $this->assertSame(Role::Mentor, Role::fromName('mentor'));
    }

    /** @return array<string, array{string}> */
    public static function unknownRoles(): array
    {
        return [
            'vacío' => [''],
            'otro rol' => ['participant'],
            'mayúsculas' => ['ADMIN'],
            'superusuario' => ['root'],
        ];
    }

    #[DataProvider('unknownRoles')]
    public function test_any_other_role_is_rejected(string $role): void
    {
        try {
            Role::fromName($role);
            $this->fail("Debió rechazar el rol «{$role}»");
        } catch (ValidationError $error) {
            $this->assertSame('role', $error->details()[0]['field']);
        }
    }
}
