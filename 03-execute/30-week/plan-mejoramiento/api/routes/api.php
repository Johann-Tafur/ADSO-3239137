<?php

use App\Adapters\Inbound\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Todas las rutas llevan el prefijo /api/v1 (bootstrap/app.php). Contrato: docs/api-contract.md

Route::get('/health', HealthController::class);
