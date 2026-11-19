<?php

use App\Adapters\Inbound\Console\EnsureAdminCommand;
use App\Adapters\Inbound\Http\ApiErrorRenderer;
use App\Adapters\Inbound\Http\Middleware\AuthenticateWithToken;
use App\Adapters\Inbound\Http\Middleware\RequireRole;
use App\Domain\Errors\DomainError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
    )
    ->withCommands([EnsureAdminCommand::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AuthenticateWithToken::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Los errores de negocio son respuestas esperadas (409, 422…), no fallas: no van al log de errores
        $exceptions->dontReport(DomainError::class);

        // Todo error bajo /api sale con el cuerpo único { code, message, details } (architecture.md §5, RNF-4)
        $exceptions->render(fn (Throwable $error, Request $request) => $request->is('api/*')
            ? ApiErrorRenderer::render($error)
            : null);
    })->create();
