<?php

use App\Adapters\Inbound\Http\Controllers\AuthController;
use App\Adapters\Inbound\Http\Controllers\HealthController;
use App\Adapters\Inbound\Http\Controllers\KitController;
use App\Adapters\Inbound\Http\Controllers\LoanController;
use App\Adapters\Inbound\Http\Controllers\ParticipantController;
use App\Adapters\Inbound\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Todas las rutas llevan el prefijo /api/v1 (bootstrap/app.php). Contrato: docs/api-contract.md §3

// ---------- Públicas ----------
Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login']);

// ---------- Con token (CA-01.3) ----------
Route::middleware('auth.token')->group(function () {
    // Cualquier rol autenticado
    Route::get('/categories', [KitController::class, 'categories']);
    Route::get('/kits', [KitController::class, 'index']);
    Route::get('/kits/{id}', [KitController::class, 'show'])->whereUuid('id');
    Route::get('/kits/{id}/photo', [KitController::class, 'showPhoto'])->whereUuid('id');

    // Solo admin
    Route::middleware('role:admin')->group(function () {
        Route::post('/users', [AuthController::class, 'createMentor']);
        Route::post('/kits', [KitController::class, 'store']);
        Route::put('/kits/{id}', [KitController::class, 'update'])->whereUuid('id');
        Route::delete('/kits/{id}', [KitController::class, 'destroy'])->whereUuid('id');
        Route::put('/kits/{id}/photo', [KitController::class, 'uploadPhoto'])->whereUuid('id');
        Route::delete('/kits/{id}/photo', [KitController::class, 'removePhoto'])->whereUuid('id');
        Route::get('/reports/most-requested', [ReportController::class, 'mostRequested']);
    });

    // admin y mentor
    Route::middleware('role:admin,mentor')->group(function () {
        Route::get('/participants', [ParticipantController::class, 'index']);
        Route::get('/participants/{id}', [ParticipantController::class, 'show'])->whereUuid('id');
        Route::post('/participants', [ParticipantController::class, 'store']);

        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        // /loans/overdue va ANTES de /loans/{id}, y {id} solo acepta UUID: no chocan (api-contract.md §3.6)
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show'])->whereUuid('id');
        Route::post('/loans/{loanId}/items/{itemId}/return', [LoanController::class, 'returnItem'])
            ->whereUuid(['loanId', 'itemId']);
    });
});
