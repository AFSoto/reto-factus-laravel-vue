<?php

declare(strict_types=1);

/**
 * Rutas del API REST de FactusPro.
 *
 * Prefijo automático: /api  (configurado en bootstrap/app.php)
 * Autenticación:      Laravel Sanctum con Bearer tokens
 *
 * Convenciones de respuesta:
 *   201  → recurso creado (store)
 *   204  → eliminado sin contenido (destroy)
 *   200  → cualquier otro caso de éxito
 *   422  → error de validación o regla de negocio
 *   404  → recurso no encontrado
 *   502  → error en la API de Factus
 *
 * Nota sobre orden de rutas:
 *   Las rutas estáticas (ej: /clientes/todos, /facturas/rangos) deben
 *   declararse ANTES que las rutas con parámetros dinámicos ({id}) para
 *   que Laravel no capture "todos" o "rangos" como un ID.
 */

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FacturaController;
use App\Http\Controllers\Api\ProductoController;
use Illuminate\Support\Facades\Route;

// ── Público — solo login ──────────────────────────────────────────────────────

Route::post('/login', [AuthController::class, 'login']);

// ── Protegido — requiere token Bearer de Sanctum ─────────────────────────────

Route::middleware('auth:sanctum')->group(function (): void {

    // ── Autenticación ─────────────────────────────────────────────────────────
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ── Clientes ──────────────────────────────────────────────────────────────
    // /clientes/todos debe ir ANTES de apiResource para no ser capturado como {id}
    Route::get('/clientes/todos', [ClienteController::class, 'todos']);

    Route::apiResource('/clientes', ClienteController::class)
        ->parameters(['clientes' => 'id']);
    // Genera: GET|POST /clientes, GET|PUT|DELETE /clientes/{id}

    // ── Productos ─────────────────────────────────────────────────────────────
    Route::get('/productos/todos', [ProductoController::class, 'todos']);

    Route::apiResource('/productos', ProductoController::class)
        ->parameters(['productos' => 'id']);

    // ── Facturas ──────────────────────────────────────────────────────────────
    // Rutas estáticas y de acciones Factus ANTES del apiResource
    Route::get('/facturas/rangos',         [FacturaController::class, 'rangos']);
    Route::post('/facturas/{id}/emitir',   [FacturaController::class, 'emitir']);
    Route::post('/facturas/{id}/anular',   [FacturaController::class, 'anular']);

    Route::apiResource('/facturas', FacturaController::class)
        ->parameters(['facturas' => 'id']);
    // Genera: GET|POST /facturas, GET|PUT|DELETE /facturas/{id}
});
