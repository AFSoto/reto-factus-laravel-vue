<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Factura\CreateFacturaDTO;
use App\DTOs\Factura\FacturasFilterDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Factura\AnularFacturaRequest;
use App\Http\Requests\Factura\StoreFacturaRequest;
use App\Http\Requests\Factura\UpdateFacturaRequest;
use App\Http\Resources\FacturaCollection;
use App\Http\Resources\FacturaResource;
use App\Services\FacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de facturación electrónica.
 *
 * Rutas (prefijo /api, middleware auth:sanctum):
 *   GET    /facturas                  → index()   — listado paginado con filtros
 *   GET    /facturas/rangos           → rangos()  — rangos de numeración Factus
 *   POST   /facturas                  → store()   — crear borrador
 *   GET    /facturas/{id}             → show()    — detalle con cliente e ítems
 *   PUT    /facturas/{id}             → update()  — actualizar borrador
 *   DELETE /facturas/{id}             → destroy() — eliminar borrador
 *   POST   /facturas/{id}/emitir      → emitir()  — emitir ante la DIAN vía Factus
 *   POST   /facturas/{id}/anular      → anular()  — anular factura emitida
 */
class FacturaController extends Controller
{
    public function __construct(
        private readonly FacturaService $facturaService,
    ) {}

    /**
     * Retorna las facturas paginadas con filtros opcionales.
     * Filtros por query string: estado, cliente_id, fecha_desde, fecha_hasta, busqueda, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros   = FacturasFilterDTO::fromArray($request->query());
        $paginator = $this->facturaService->listar($request->user()->id, $filtros);

        return (new FacturaCollection($paginator))->response();
    }

    /**
     * Retorna los rangos de numeración disponibles desde la API de Factus.
     * El frontend los usa para el selector al crear o editar una factura.
     */
    public function rangos(Request $request): JsonResponse
    {
        $rangos = $this->facturaService->obtenerRangosNumeracion();

        return response()->json(['data' => $rangos]);
    }

    /**
     * Crea una nueva factura en estado borrador.
     *
     * @return JsonResponse  HTTP 201 con la factura creada
     */
    public function store(StoreFacturaRequest $request): JsonResponse
    {
        $dto     = CreateFacturaDTO::fromArray($request->validated(), $request->user()->id);
        $factura = $this->facturaService->crear($dto);

        return FacturaResource::make($factura)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retorna el detalle de una factura con su cliente e ítems.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $factura = $this->facturaService->buscar($id, $request->user()->id);

        return FacturaResource::make($factura)->response();
    }

    /**
     * Actualiza una factura en estado borrador.
     * Las facturas emitidas o anuladas no pueden modificarse.
     */
    public function update(UpdateFacturaRequest $request, int $id): JsonResponse
    {
        $dto     = CreateFacturaDTO::fromArray($request->validated(), $request->user()->id);
        $factura = $this->facturaService->actualizar($id, $dto, $request->user()->id);

        return FacturaResource::make($factura)->response();
    }

    /**
     * Elimina (soft delete) una factura en estado borrador.
     *
     * @return JsonResponse  HTTP 204 sin contenido
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->facturaService->eliminar($id, $request->user()->id);

        return response()->json(null, 204);
    }

    /**
     * Emite la factura ante la DIAN vía la API de Factus.
     * Solo los borradores con ítems pueden emitirse.
     */
    public function emitir(Request $request, int $id): JsonResponse
    {
        $factura = $this->facturaService->emitir($id, $request->user()->id);

        return FacturaResource::make($factura)->response();
    }

    /**
     * Anula una factura emitida ante la DIAN vía la API de Factus.
     * Solo las facturas emitidas pueden anularse.
     */
    public function anular(AnularFacturaRequest $request, int $id): JsonResponse
    {
        $motivo  = $request->validated('motivo');
        $factura = $this->facturaService->anular($id, $motivo, $request->user()->id);

        return FacturaResource::make($factura)->response();
    }
}
