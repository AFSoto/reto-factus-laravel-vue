<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Implementación Eloquent del repositorio de productos.
 *
 * Única capa autorizada a usar Eloquent sobre la tabla 'productos'.
 * Implementa ProductoRepositoryInterface para garantizar el contrato
 * con los Services que lo consumen.
 */

use App\DTOs\Producto\CreateProductoDTO;
use App\Models\Producto;
use App\Repositories\Contracts\ProductoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductoRepository implements ProductoRepositoryInterface
{
    /**
     * Retorna los productos del usuario paginados con filtros opcionales.
     *
     * Soporta búsqueda por texto (nombre o código) y filtro por estado activo.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  busqueda (string), activo (bool), con_iva (bool)
     * @param  int    $perPage  Registros por página
     * @return LengthAwarePaginator<Producto>
     */
    public function paginar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Producto::query()
            ->where('user_id', $userId)
            ->orderBy('nombre');

        // Búsqueda de texto libre — busca en nombre y código
        if (!empty($filtros['busqueda'])) {
            $termino = '%' . $filtros['busqueda'] . '%';
            $query->where(function ($q) use ($termino): void {
                $q->where('nombre', 'like', $termino)
                    ->orWhere('codigo', 'like', $termino)
                    ->orWhere('descripcion', 'like', $termino);
            });
        }

        // Filtro por estado activo
        if (isset($filtros['activo'])) {
            $query->where('activo', (bool) $filtros['activo']);
        }

        // Filtro para productos que tienen IVA (porcentaje > 0)
        if (isset($filtros['con_iva']) && $filtros['con_iva'] === true) {
            $query->where('porcentaje_iva', '>', 0);
        }

        return $query->paginate($perPage);
    }

    /**
     * Retorna todos los productos activos del usuario sin paginar.
     * Ordenados por nombre para los selectores del frontend.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return Collection<int, Producto>
     */
    public function todos(int $userId): Collection
    {
        return Producto::query()
            ->where('user_id', $userId)
            ->activos()
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Busca un producto por ID asegurando que pertenece al usuario.
     *
     * @param  int  $id      ID del producto
     * @param  int  $userId  ID del usuario autenticado
     * @return Producto|null
     */
    public function buscarPorId(int $id, int $userId): ?Producto
    {
        return Producto::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Busca un producto por su código interno del usuario.
     *
     * @param  string  $codigo  Código único del producto
     * @param  int     $userId  ID del usuario autenticado
     * @return Producto|null
     */
    public function buscarPorCodigo(string $codigo, int $userId): ?Producto
    {
        return Producto::query()
            ->where('user_id', $userId)
            ->where('codigo', $codigo)
            ->first();
    }

    /**
     * Crea y persiste un nuevo producto desde el DTO.
     *
     * @param  CreateProductoDTO  $dto  DTO con los datos validados del producto
     * @return Producto  La instancia creada con su ID asignado
     */
    public function crear(CreateProductoDTO $dto): Producto
    {
        return Producto::create($dto->toArray());
    }

    /**
     * Actualiza los atributos de un producto existente desde el DTO.
     *
     * @param  Producto           $producto  Instancia a actualizar
     * @param  CreateProductoDTO  $dto       DTO con los nuevos datos validados
     * @return Producto  La instancia con los datos actualizados
     */
    public function actualizar(Producto $producto, CreateProductoDTO $dto): Producto
    {
        $producto->update($dto->toArray());

        return $producto->refresh();
    }

    /**
     * Soft-delete de un producto (no elimina el registro físico).
     * Los ítems de facturas que referencian este producto no se ven afectados
     * porque el producto_id en factura_items es nullable con nullOnDelete.
     *
     * @param  Producto  $producto  Instancia a eliminar
     * @return bool  True si se eliminó exitosamente
     */
    public function eliminar(Producto $producto): bool
    {
        return (bool) $producto->delete();
    }

    /**
     * Verifica si ya existe un producto con ese código para el usuario.
     *
     * @param  string    $codigo     Código a verificar
     * @param  int       $userId     ID del usuario autenticado
     * @param  int|null  $excluirId  ID del producto a excluir (para actualizaciones)
     * @return bool
     */
    public function existeCodigo(string $codigo, int $userId, ?int $excluirId = null): bool
    {
        $query = Producto::query()
            ->where('user_id', $userId)
            ->where('codigo', $codigo);

        if ($excluirId !== null) {
            $query->where('id', '!=', $excluirId);
        }

        return $query->exists();
    }
}
