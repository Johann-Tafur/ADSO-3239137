<?php

namespace App\Adapters\Outbound\Security;

use App\Application\Ports\Clock;
use App\Application\Ports\TokenIssuer;
use App\Application\Results\AccessClaims;
use App\Domain\Role;
use App\Domain\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use Throwable;

/**
 * TokenIssuer con JWT HS256 de firebase/php-jwt (plan D-11, api-contract.md §4).
 *
 * - Claims: `sub` (id del usuario), `role`, `iat` y `exp` = iat + 8 h. Ningún dato personal: ni nombre ni clave.
 * - La firma usa JWT_SECRET; sin ella no se puede fabricar un token. Mínimo 32 bytes (256 bits, lo que pide HS256).
 * - Solo acepta HS256: un token que diga «alg: none» u otro algoritmo se rechaza.
 * - La hora sale del Clock, así las pruebas comprueban el vencimiento sin esperar 8 horas.
 */
final class JwtTokenIssuer implements TokenIssuer
{
    private const ALGORITHM = 'HS256';

    private const LIFETIME_SECONDS = 8 * 3600;

    private const MIN_SECRET_BYTES = 32;

    public function __construct(
        #[\SensitiveParameter] private readonly string $secret,
        private readonly Clock $clock,
    ) {
        if (strlen($secret) < self::MIN_SECRET_BYTES) {
            throw new InvalidArgumentException('JWT_SECRET debe tener al menos '.self::MIN_SECRET_BYTES.' caracteres.');
        }
    }

    public function issue(User $user): string
    {
        $issuedAt = $this->clock->now()->getTimestamp();

        return JWT::encode([
            'sub' => $user->id(),
            'role' => $user->role()->value,
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::LIFETIME_SECONDS,
        ], $this->secret, self::ALGORITHM);
    }

    public function lifetimeSeconds(): int
    {
        return self::LIFETIME_SECONDS;
    }

    public function verify(string $token): ?AccessClaims
    {
        // La librería compara exp con JWT::$timestamp; se le da la hora del Clock y se deja como estaba
        $previous = JWT::$timestamp;
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $claims = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable) {
            return null; // mal formado, firma inválida, otro algoritmo o vencido: para la API todo es 401
        } finally {
            JWT::$timestamp = $previous;
        }

        if (! isset($claims->exp, $claims->sub, $claims->role) || ! is_string($claims->sub)) {
            return null;
        }
        $role = Role::tryFrom((string) $claims->role);

        return $role === null ? null : new AccessClaims($claims->sub, $role);
    }
}
