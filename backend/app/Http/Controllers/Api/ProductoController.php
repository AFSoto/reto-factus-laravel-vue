<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Producto\StoreProductoRequest;
use App\Http\Requests\Producto\UpdateProductoRequest;
use App\Http\Resources\ProductoResource;
use App\Services\ProductoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador CRUD del catálogo de productos.
 *
 * Rutas (prefijo /api, middleware auth:sanctum):
 *   GET    /productos          → index()   — listado paginado con filtros
 *   GET    /productos/todos    → todos()   — todos activos para selectores
 *   POST   /productos          → store()   — crear producto
 *   GET    /productos/{id}     → show()    — detalle de un producto
 *   PUT    /productos/{id}     → update()  — actualizar producto
 *   DELETE /productos/{id}     → destroy() — soft delete
 */
class ProductoController extends Controller
{
    public function __construct(
        private readonly ProductoService $productoService,
    ) {}

    /**
     * Retorna los productos paginados con filtros opcionales.
     * Filtros por query string: busqueda, activo, con_iva, per_page.
     */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->only(['busqueda', 'activo', 'con_iva']);
        $perPage = (int) $request->input('per_page', 15);

        $paginator = $this->productoService->listar(
            $request->user()->id,
            $filtros,
            $perPage
        );

        return ProductoResource::collection($paginator)->response();
    }

    /**
     * Retorna todos los productos activos sin paginar (para el selector de ítems).
     */
    public function todos(Request $request): JsonResponse
    {
        $productos = $this->productoService->todos($request->user()->id);

        return ProductoResource::collection($productos)->response();
    }

    /**
     * Crea un nuevo producto en el catálogo.
     *
     * @return JsonResponse  HTTP 201 con el producto creado
     */
    public function store(StoreProductoRequest $request): JsonResponse
    {
        $producto = $this->productoService->crear(
            $request->validated(),
            $request->user()->id
        );

        return ProductoResource::make($producto)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retorna el detalle de un producto específico.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $producto = $this->productoService->buscar($id, $request->user()->id);

        return ProductoResource::make($producto)->response();
    }

    /**
     * Actualiza todos los datos de un producto existente.
     */
    public function update(UpdateProductoRequest $request, int $id): JsonResponse
    {
        $producto = $this->productoService->actualizar(
            $id,
            $request->validated(),
            $request->user()->id
        );

        return ProductoResource::make($producto)->response();
    }

    /**
     * Elimina (soft delete) un producto del catálogo.
     *
     * @return JsonResponse  HTTP 204 sin contenido
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->productoService->eliminar($id, $request->user()->id);

        return response()->json(null, 204);
    }
}
