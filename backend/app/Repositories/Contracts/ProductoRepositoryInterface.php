<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Contrato del repositorio de productos.
 *
 * Define todas las operaciones de persistencia sobre la entidad Producto.
 * Los Services consumen solo esta interfaz para desacoplarse de Eloquent.
 *
 * Convención de filtros para paginar():
 *   - 'busqueda'  (string): filtra por nombre o código
 *   - 'activo'    (bool): filtra por estado activo/inactivo
 *   - 'con_iva'   (bool): filtra productos con IVA > 0
 */

use App\Models\Producto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ProductoRepositoryInterface
{
    /**
     * Retorna los productos del usuario paginados con filtros opcionales.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  Filtros opcionales: busqueda, activo, con_iva
     * @param  int    $perPage  Registros por página (default 15)
     * @return LengthAwarePaginator<Producto>
     */
    public function paginar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Retorna todos los productos activos del usuario sin paginar.
     * Usado para selectores al agregar ítems a una factura.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return Collection<int, Producto>
     */
    public function todos(int $userId): Collection;

    /**
     * Busca un producto por su ID verificando pertenencia al usuario.
     *
     * @param  int  $id      ID del producto a buscar
     * @param  int  $userId  ID del usuario autenticado
     * @return Producto|null  El producto o null si no existe o no pertenece al usuario
     */
    public function buscarPorId(int $id, int $userId): ?Producto;

    /**
     * Busca un producto por su código interno.
     *
     * @param  string  $codigo   Código único del producto dentro del catálogo del usuario
     * @param  int     $userId   ID del usuario autenticado
     * @return Producto|null
     */
    public function buscarPorCodigo(string $codigo, int $userId): ?Producto;

    /**
     * Crea un nuevo producto en el catálogo del usuario.
     *
     * @param  array{
     *     user_id: int,
     *     codigo: string,
     *     nombre: string,
     *     descripcion?: string|null,
     *     unidad_medida_id: string,
     *     tipo_item_identificacion_id: string,
     *     codigo_referencia?: string|null,
     *     precio_unitario: float,
     *     porcentaje_descuento?: float,
     *     porcentaje_iva: float,
     *     tribute_id: int,
     *     activo?: bool
     * }  $data  Datos del producto a crear
     * @return Producto  El producto recién creado
     */
    public function crear(array $data): Producto;

    /**
     * Actualiza los datos de un producto existente.
     *
     * @param  Producto  $producto  Instancia del producto a actualizar
     * @param  array     $data      Campos a modificar (misma forma que crear())
     * @return Producto  El producto con los datos actualizados
     */
    public function actualizar(Producto $producto, array $data): Producto;

    /**
     * Elimina (soft delete) un producto del catálogo.
     *
     * @param  Producto  $producto  Instancia del producto a eliminar
     * @return bool  True si se eliminó exitosamente
     */
    public function eliminar(Producto $producto): bool;

    /**
     * Verifica si ya existe un producto con el mismo código para el usuario.
     * Permite excluir un ID para validaciones de actualización.
     *
     * @param  string    $codigo     Código a verificar
     * @param  int       $userId     ID del usuario autenticado
     * @param  int|null  $excluirId  ID del producto a excluir de la búsqueda
     * @return bool  True si ya existe otro producto con ese código
     */
    public function existeCodigo(string $codigo, int $userId, ?int $excluirId = null): bool;
}
