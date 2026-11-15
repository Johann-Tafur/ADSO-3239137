<?php

namespace App\Adapters\Inbound\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/health — público (api-contract.md §3, CA-01.3).
 * Solo dice que la API está viva; no consulta la base.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
