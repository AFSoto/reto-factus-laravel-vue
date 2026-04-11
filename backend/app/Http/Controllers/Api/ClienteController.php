<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Http\Resources\ClienteResource;
use App\Services\ClienteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador CRUD de clientes.
 *
 * Rutas (prefijo /api, middleware auth:sanctum):
 *   GET    /clientes          → index()   — listado paginado con filtros
 *   GET    /clientes/todos    → todos()   — todos activos para selectores
 *   POST   /clientes          → store()   — crear cliente
 *   GET    /clientes/{id}     → show()    — detalle de un cliente
 *   PUT    /clientes/{id}     → update()  — actualizar cliente
 *   DELETE /clientes/{id}     → destroy() — soft delete
 */
class ClienteController extends Controller
{
    public function __construct(
        private readonly ClienteService $clienteService,
    ) {}

    /**
     * Retorna los clientes paginados con filtros opcionales.
     * Filtros por query string: busqueda, tipo_persona, activo, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['busqueda', 'tipo_persona', 'activo']);
        $perPage = (int) $request->input('per_page', 15);

        $paginator = $this->clienteService->listar(
            $request->user()->id,
            $filtros,
            $perPage
        );

        return ClienteResource::collection($paginator)->response();
    }

    /**
     * Retorna todos los clientes activos sin paginar (para selectores del frontend).
     */
    public function todos(Request $request): JsonResponse
    {
        $clientes = $this->clienteService->todos($request->user()->id);

        return ClienteResource::collection($clientes)->response();
    }

    /**
     * Crea un nuevo cliente.
     *
     * @return JsonResponse  HTTP 201 con el cliente creado
     */
    public function store(StoreClienteRequest $request): JsonResponse
    {
        $cliente = $this->clienteService->crear(
            $request->validated(),
            $request->user()->id
        );

        return ClienteResource::make($cliente)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retorna el detalle de un cliente específico.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $cliente = $this->clienteService->buscar($id, $request->user()->id);

        return ClienteResource::make($cliente)->response();
    }

    /**
     * Actualiza todos los datos de un cliente existente.
     */
    public function update(UpdateClienteRequest $request, int $id): JsonResponse
    {
        $cliente = $this->clienteService->actualizar(
            $id,
            $request->validated(),
            $request->user()->id
        );

        return ClienteResource::make($cliente)->response();
    }

    /**
     * Elimina (soft delete) un cliente.
     *
     * @return JsonResponse  HTTP 204 sin contenido
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->clienteService->eliminar($id, $request->user()->id);

        return response()->json(null, 204);
    }
}
