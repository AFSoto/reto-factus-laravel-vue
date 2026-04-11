<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource del dashboard de métricas.
 * Recibe el array de DashboardService::obtenerMetricas() y lo transforma para el frontend.
 */
class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'conteo_por_estado'   => $this->resource['conteo_por_estado'],
            'total_facturado_mes' => $this->resource['total_facturado_mes'],
            'facturas_recientes'  => FacturaResource::collection($this->resource['facturas_recientes']),
            'total_clientes'      => $this->resource['total_clientes'],
            'total_productos'     => $this->resource['total_productos'],
            'mes_actual'          => $this->resource['mes_actual'],
            'anio_actual'         => $this->resource['anio_actual'],
        ];
    }
}
