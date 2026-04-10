<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Implementación Eloquent del repositorio de clientes.
 *
 * Única capa del sistema autorizada a usar Eloquent sobre la tabla 'clientes'.
 * Ningún Service, Controller ni otro componente debe importar el modelo Cliente
 * directamente para hacer queries — todo pasa por esta clase.
 *
 * Implementa ClienteRepositoryInterface para garantizar el contrato con los Services.
 */

use App\DTOs\Cliente\CreateClienteDTO;
use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClienteRepository implements ClienteRepositoryInterface
{
    /**
     * Retorna los clientes del usuario paginados con filtros opcionales.
     *
     * Soporta búsqueda por texto (razon_social, nombre, número de documento),
     * filtro por tipo de persona y estado activo/inactivo.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  busqueda (string), tipo_persona (string), activo (bool)
     * @param  int    $perPage  Registros por página
     * @return LengthAwarePaginator<Cliente>
     */
    public function paginar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Cliente::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Búsqueda de texto libre — busca en razón social, nombres y documento
        if (!empty($filtros['busqueda'])) {
            $termino = '%' . $filtros['busqueda'] . '%';
            $query->where(function ($q) use ($termino): void {
                $q->where('razon_social', 'like', $termino)
                    ->orWhere('primer_nombre', 'like', $termino)
                    ->orWhere('primer_apellido', 'like', $termino)
                    ->orWhere('numero_documento', 'like', $termino)
                    ->orWhere('email', 'like', $termino);
            });
        }

        // Filtro por tipo de persona (J=Jurídica, N=Natural)
        if (isset($filtros['tipo_persona']) && $filtros['tipo_persona'] !== '') {
            $query->where('tipo_persona', $filtros['tipo_persona']);
        }

        // Filtro por estado activo — si no viene el filtro, muestra todos
        if (isset($filtros['activo'])) {
            $query->where('activo', (bool) $filtros['activo']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Retorna todos los clientes activos del usuario sin paginar.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return Collection<int, Cliente>
     */
    public function todos(int $userId): Collection
    {
        return Cliente::query()
            ->where('user_id', $userId)
            ->activos()
            ->orderBy('razon_social')
            ->orderBy('primer_apellido')
            ->get();
    }

    /**
     * Busca un cliente por ID asegurando que pertenece al usuario.
     *
     * @param  int  $id      ID del cliente
     * @param  int  $userId  ID del usuario autenticado
     * @return Cliente|null
     */
    public function buscarPorId(int $id, int $userId): ?Cliente
    {
        return Cliente::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Busca un cliente por tipo y número de documento del usuario.
     *
     * @param  string  $numero  Número de documento
     * @param  int     $tipo    Código DIAN del tipo de documento
     * @param  int     $userId  ID del usuario autenticado
     * @return Cliente|null
     */
    public function buscarPorDocumento(string $numero, int $tipo, int $userId): ?Cliente
    {
        return Cliente::query()
            ->where('user_id', $userId)
            ->where('numero_documento', $numero)
            ->where('tipo_documento_identidad_id', $tipo)
            ->first();
    }

    /**
     * Crea y persiste un nuevo cliente desde el DTO.
     *
     * @param  CreateClienteDTO  $dto  DTO con los datos validados del cliente
     * @return Cliente  La instancia creada con su ID asignado
     */
    public function crear(CreateClienteDTO $dto): Cliente
    {
        return Cliente::create($dto->toArray());
    }

    /**
     * Actualiza los atributos de un cliente existente desde el DTO.
     *
     * @param  Cliente           $cliente  Instancia a actualizar
     * @param  CreateClienteDTO  $dto      DTO con los nuevos datos validados
     * @return Cliente  La instancia con los datos actualizados
     */
    public function actualizar(Cliente $cliente, CreateClienteDTO $dto): Cliente
    {
        $cliente->update($dto->toArray());

        return $cliente->refresh();
    }

    /**
     * Soft-delete de un cliente (marca deleted_at, no elimina el registro).
     *
     * @param  Cliente  $cliente  Instancia a eliminar
     * @return bool  True si se eliminó exitosamente
     */
    public function eliminar(Cliente $cliente): bool
    {
        return (bool) $cliente->delete();
    }

    /**
     * Verifica si ya existe un cliente con ese documento para el usuario.
     * Excluye el ID indicado para permitir validaciones en actualizaciones.
     *
     * @param  string    $numero     Número de documento
     * @param  int       $tipo       Código DIAN del tipo de documento
     * @param  int       $userId     ID del usuario autenticado
     * @param  int|null  $excluirId  ID del cliente a excluir de la búsqueda
     * @return bool
     */
    public function existeDocumento(
        string $numero,
        int $tipo,
        int $userId,
        ?int $excluirId = null
    ): bool {
        $query = Cliente::query()
            ->where('user_id', $userId)
            ->where('numero_documento', $numero)
            ->where('tipo_documento_identidad_id', $tipo);

        if ($excluirId !== null) {
            $query->where('id', '!=', $excluirId);
        }

        return $query->exists();
    }
}
