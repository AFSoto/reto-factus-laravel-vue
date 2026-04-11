<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador del dashboard de métricas.
 *
 * Rutas (prefijo /api, middleware auth:sanctum):
 *   GET /dashboard → index() — resumen de métricas del usuario
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * Retorna las métricas del dashboard para el usuario autenticado.
     *
     * @return JsonResponse  Conteos por estado, total facturado, facturas recientes, etc.
     */
    public function index(Request $request): JsonResponse
    {
        $metricas = $this->dashboardService->obtenerMetricas($request->user()->id);

        return DashboardResource::make($metricas)->response();
    }
}
