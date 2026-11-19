<?php

namespace Tests\Unit\Adapters;

use App\Adapters\Outbound\Security\JwtTokenIssuer;
use App\Domain\Role;
use App\Domain\User;
use Firebase\JWT\JWT;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\FixedClock;

/** T-14 · Token JWT HS256 de 8 horas con `sub` y `role` (CA-01.1, CA-01.3, plan D-11, api-contract.md §4). */
final class JwtTokenIssuerTest extends TestCase
{
    private const SECRET = 'secreto-de-pruebas-con-mas-de-32-caracteres';

    private FixedClock $clock;

    private JwtTokenIssuer $tokens;

    private User $laura;

    protected function setUp(): void
    {
        $this->clock = new FixedClock('2026-11-17T08:00:00Z');
        $this->tokens = new JwtTokenIssuer(self::SECRET, $this->clock);
        $this->laura = User::create('4f1c2a9e-0000-4000-8000-000000000001', 'laura', 'hash-argon', Role::Mentor);
    }

    /** @return array<string, mixed> el cuerpo del token, leído sin verificar */
    private static function payload(string $token): array
    {
        return json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
    }

    public function test_the_token_carries_sub_role_and_lasts_8_hours(): void
    {
        $payload = self::payload($this->tokens->issue($this->laura));

        $this->assertSame($this->laura->id(), $payload['sub']);
        $this->assertSame('mentor', $payload['role']);
        $this->assertSame(28800, $payload['exp'] - $payload['iat']);
        $this->assertSame(28800, $this->tokens->lifetimeSeconds());
    }

    public function test_the_token_has_no_personal_data_nor_the_hash(): void
    {
        $payload = self::payload($this->tokens->issue($this->laura));

        $claims = array_keys($payload);
        sort($claims);
        $this->assertSame(['exp', 'iat', 'role', 'sub'], $claims, 'solo estas cuatro claims');
        $this->assertStringNotContainsString('laura', json_encode($payload));
        $this->assertStringNotContainsString('hash-argon', json_encode($payload));
    }

    public function test_a_valid_token_gives_back_who_and_with_which_role(): void
    {
        $claims = $this->tokens->verify($this->tokens->issue($this->laura));

        $this->assertSame($this->laura->id(), $claims->userId);
        $this->assertSame(Role::Mentor, $claims->role);
    }

    public function test_it_is_valid_until_the_8th_hour_and_expired_after(): void
    {
        $token = $this->tokens->issue($this->laura);

        $this->clock->advance('+7 hours 59 minutes');
        $this->assertNotNull($this->tokens->verify($token));

        $this->clock->advance('+2 minutes');
        $this->assertNull($this->tokens->verify($token), 'pasadas las 8 horas el token ya no sirve');
    }

    public function test_a_token_signed_with_another_secret_is_rejected(): void
    {
        $forged = (new JwtTokenIssuer('otro-secreto-tambien-de-mas-de-32-caracteres', $this->clock))->issue($this->laura);

        $this->assertNull($this->tokens->verify($forged));
    }

    public function test_changing_the_role_inside_the_token_breaks_the_signature(): void
    {
        [$header, , $signature] = explode('.', $this->tokens->issue($this->laura));
        $payload = rtrim(strtr(base64_encode(json_encode([
            'sub' => $this->laura->id(), 'role' => 'admin', 'iat' => time(), 'exp' => time() + 3600,
        ])), '+/', '-_'), '=');

        $this->assertNull($this->tokens->verify("{$header}.{$payload}.{$signature}"), 'un mentor no se vuelve admin editando el token');
    }

    public function test_a_token_with_alg_none_is_rejected(): void
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'none', 'typ' => 'JWT']).'.'.$encode(['sub' => 'x', 'role' => 'admin', 'exp' => time() + 3600]).'.';

        $this->assertNull($this->tokens->verify($unsigned));
    }

    public function test_garbage_is_rejected_without_exceptions(): void
    {
        foreach (['', 'hola', 'a.b.c', 'Bearer xyz'] as $garbage) {
            $this->assertNull($this->tokens->verify($garbage));
        }
    }

    public function test_an_unknown_role_inside_a_well_signed_token_is_rejected(): void
    {
        $token = JWT::encode(['sub' => 'x', 'role' => 'root', 'iat' => 0, 'exp' => PHP_INT_MAX], self::SECRET, 'HS256');

        $this->assertNull($this->tokens->verify($token));
    }

    public function test_a_short_secret_is_refused_at_startup(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new JwtTokenIssuer('corto', $this->clock);
    }
}
