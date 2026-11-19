<?php

namespace App\Adapters\Inbound\Http\Middleware;

use App\Adapters\Inbound\Http\ApiErrorRenderer;
use App\Application\Results\AccessClaims;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autorización por rol en cada ruta: `role:admin` o `role:admin,mentor` (CA-01.3, api-contract.md §4).
 * Corre ANTES del controlador, así un 403 no revela si el recurso existe.
 */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var AccessClaims|null $claims */
        $claims = $request->attributes->get(AuthenticateWithToken::CLAIMS);
        if ($claims === null || ! in_array($claims->role->value, $roles, true)) {
            return ApiErrorRenderer::response('FORBIDDEN_ROLE', 'Tu rol no puede hacer esta operación.', 403);
        }

        return $next($request);
    }
}
