<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de métricas para el dashboard.
 *
 * Agrega datos de los repositorios de facturas, clientes y productos
 * para construir el resumen estadístico que muestra el dashboard al iniciar sesión.
 *
 * Métricas que retorna obtenerMetricas():
 *   - conteo_por_estado:    { borrador: N, emitida: N, anulada: N }
 *   - total_facturado_mes:  Suma de totales de facturas emitidas en el mes actual
 *   - facturas_recientes:   Últimas 5 facturas con relación cliente
 *   - total_clientes:       Cantidad de clientes activos del usuario
 *   - total_productos:      Cantidad de productos activos del usuario
 */

use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use App\Repositories\Contracts\ProductoRepositoryInterface;

class DashboardService
{
    /**
     * @param  FacturaRepositoryInterface  $facturaRepository  Repositorio de facturas
     * @param  ClienteRepositoryInterface  $clienteRepository  Repositorio de clientes
     * @param  ProductoRepositoryInterface $productoRepository Repositorio de productos
     */
    public function __construct(
        private readonly FacturaRepositoryInterface $facturaRepository,
        private readonly ClienteRepositoryInterface $clienteRepository,
        private readonly ProductoRepositoryInterface $productoRepository,
    ) {}

    /**
     * Retorna el resumen de métricas para el dashboard del usuario.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return array{
     *     conteo_por_estado: array{borrador: int, emitida: int, anulada: int},
     *     total_facturado_mes: float,
     *     facturas_recientes: \Illuminate\Database\Eloquent\Collection,
     *     total_clientes: int,
     *     total_productos: int,
     *     mes_actual: int,
     *     anio_actual: int
     * }
     */
    public function obtenerMetricas(int $userId): array
    {
        $mesActual  = (int) date('n');
        $anioActual = (int) date('Y');

        return [
            'conteo_por_estado'   => $this->facturaRepository->contarPorEstado($userId),
            'total_facturado_mes' => $this->facturaRepository->totalFacturadoMes($userId, $mesActual, $anioActual),
            'facturas_recientes'  => $this->facturaRepository->facturasRecientes($userId, 5),
            'total_clientes'      => $this->clienteRepository->todos($userId)->count(),
            'total_productos'     => $this->productoRepository->todos($userId)->count(),
            'mes_actual'          => $mesActual,
            'anio_actual'         => $anioActual,
        ];
    }
}
