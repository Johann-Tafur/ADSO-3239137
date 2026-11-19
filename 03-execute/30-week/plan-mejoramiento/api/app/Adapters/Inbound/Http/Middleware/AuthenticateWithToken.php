<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Adapters\Inbound\Http\ApiErrorRenderer;
use App\Application\Ports\TokenIssuer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige `Authorization: Bearer <token>` válido y vigente (CA-01.3). Sin token, mal firmado o vencido → 401 UNAUTHENTICATED.
 * Deja las claims (id y rol) en la petición para RequireRole y para los controladores.
 */
final class AuthenticateWithToken
{
    public const CLAIMS = 'robotics.claims';

    public function __construct(private readonly TokenIssuer $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token === null ? null : $this->tokens->verify($token);
        if ($claims === null) {
            return ApiErrorRenderer::response('UNAUTHENTICATED', 'Inicia sesión para continuar.', 401);
        }
        $request->attributes->set(self::CLAIMS, $claims);

        return $next($request);
    }
}
