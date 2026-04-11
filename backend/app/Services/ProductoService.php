<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de gestión del catálogo de productos.
 *
 * Contiene toda la lógica de negocio relacionada con productos:
 *   - Listar/paginar con filtros opcionales
 *   - Obtener todos los productos activos (para el selector al crear ítems de factura)
 *   - Buscar por ID con validación de pertenencia al usuario
 *   - Crear, actualizar y eliminar (soft delete)
 *
 * Siempre verifica que el recurso pertenezca al usuario autenticado.
 * Lanza ProductoNotFoundException si el producto no existe o no pertenece al usuario.
 *
 * Flujo estándar:
 *   Controller → ProductoService → ProductoRepositoryInterface → DB
 */

use App\DTOs\Producto\CreateProductoDTO;
use App\Exceptions\ProductoNotFoundException;
use App\Models\Producto;
use App\Repositories\Contracts\ProductoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductoService
{
    /**
     * @param  ProductoRepositoryInterface  $productoRepository  Repositorio de productos inyectado por IoC
     */
    public function __construct(
        private readonly ProductoRepositoryInterface $productoRepository,
    ) {}

    // ── Consultas ─────────────────────────────────────────────────────────────

    /**
     * Retorna los productos del usuario paginados con filtros opcionales.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  Filtros: busqueda (string), activo (bool), con_iva (bool)
     * @param  int    $perPage  Registros por página
     * @return LengthAwarePaginator<Producto>
     */
    public function listar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->productoRepository->paginar($userId, $filtros, $perPage);
    }

    /**
     * Retorna todos los productos activos del usuario sin paginar.
     * Útil para el selector de productos al agregar ítems a una factura.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return Collection<int, Producto>
     */
    public function todos(int $userId): Collection
    {
        return $this->productoRepository->todos($userId);
    }

    /**
     * Busca un producto por su ID verificando pertenencia al usuario.
     *
     * @param  int  $id      ID del producto a buscar
     * @param  int  $userId  ID del usuario autenticado
     * @return Producto  El producto encontrado
     * @throws ProductoNotFoundException  Si no existe o no pertenece al usuario
     */
    public function buscar(int $id, int $userId): Producto
    {
        $producto = $this->productoRepository->buscarPorId($id, $userId);

        if ($producto === null) {
            throw ProductoNotFoundException::conId($id);
        }

        return $producto;
    }

    // ── Mutaciones ────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo producto en el catálogo del usuario.
     * Construye el DTO desde el array y delega la persistencia al repositorio.
     *
     * @param  array  $data    Datos validados del FormRequest
     * @param  int    $userId  ID del usuario autenticado (propietario del catálogo)
     * @return Producto  El producto recién creado
     */
    public function crear(array $data, int $userId): Producto
    {
        $dto = CreateProductoDTO::fromArray($data, $userId);

        return $this->productoRepository->crear($dto);
    }

    /**
     * Actualiza los datos de un producto existente.
     * Primero verifica que el producto exista y pertenezca al usuario.
     *
     * @param  int    $id      ID del producto a actualizar
     * @param  array  $data    Datos validados del FormRequest
     * @param  int    $userId  ID del usuario autenticado
     * @return Producto  El producto con los datos actualizados
     * @throws ProductoNotFoundException  Si no existe o no pertenece al usuario
     */
    public function actualizar(int $id, array $data, int $userId): Producto
    {
        $producto = $this->buscar($id, $userId);
        $dto      = CreateProductoDTO::fromArray($data, $userId);

        return $this->productoRepository->actualizar($producto, $dto);
    }

    /**
     * Elimina (soft delete) un producto del catálogo del usuario.
     * Primero verifica que el producto exista y pertenezca al usuario.
     *
     * @param  int  $id      ID del producto a eliminar
     * @param  int  $userId  ID del usuario autenticado
     * @return bool  True si la eliminación fue exitosa
     * @throws ProductoNotFoundException  Si no existe o no pertenece al usuario
     */
    public function eliminar(int $id, int $userId): bool
    {
        $producto = $this->buscar($id, $userId);

        return $this->productoRepository->eliminar($producto);
    }
}
