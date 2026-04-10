<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Contrato del repositorio de clientes.
 *
 * Define todas las operaciones de persistencia permitidas sobre la entidad
 * Cliente. Los Services consumen SOLO esta interfaz — nunca la implementación
 * concreta — garantizando el desacoplamiento entre capas.
 *
 * Convención de parámetros:
 *   - $userId: siempre requerido para aislar datos por usuario autenticado.
 *   - crear/actualizar reciben DTOs tipados — nunca arrays sin estructura.
 *   - Los filtros de paginación usan arrays simples de primitivas (aceptable para filtros).
 */

use App\DTOs\Cliente\CreateClienteDTO;
use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClienteRepositoryInterface
{
    /**
     * Retorna los clientes del usuario paginados con filtros opcionales.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  Filtros opcionales: busqueda, tipo_persona, activo
     * @param  int    $perPage  Cantidad de registros por página (default 15)
     * @return LengthAwarePaginator<Cliente>
     */
    public function paginar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Retorna todos los clientes activos del usuario sin paginar.
     * Útil para selectores y listas de selección en el frontend.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return Collection<int, Cliente>
     */
    public function todos(int $userId): Collection;

    /**
     * Busca un cliente por su ID verificando que pertenezca al usuario.
     *
     * @param  int  $id      ID del cliente a buscar
     * @param  int  $userId  ID del usuario autenticado
     * @return Cliente|null  El cliente o null si no existe o no pertenece al usuario
     */
    public function buscarPorId(int $id, int $userId): ?Cliente;

    /**
     * Busca un cliente por número y tipo de documento.
     *
     * @param  string  $numero   Número del documento de identidad
     * @param  int     $tipo     Código DIAN del tipo de documento (13=CC, 31=NIT, etc.)
     * @param  int     $userId   ID del usuario autenticado
     * @return Cliente|null
     */
    public function buscarPorDocumento(string $numero, int $tipo, int $userId): ?Cliente;

    /**
     * Crea un nuevo cliente con los datos del DTO.
     *
     * @param  CreateClienteDTO  $dto  DTO con los datos validados del cliente
     * @return Cliente  El cliente recién creado
     */
    public function crear(CreateClienteDTO $dto): Cliente;

    /**
     * Actualiza los datos de un cliente existente con los datos del DTO.
     *
     * @param  Cliente           $cliente  Instancia del cliente a actualizar
     * @param  CreateClienteDTO  $dto      DTO con los nuevos datos validados
     * @return Cliente  El cliente con los datos actualizados
     */
    public function actualizar(Cliente $cliente, CreateClienteDTO $dto): Cliente;

    /**
     * Elimina (soft delete) un cliente.
     *
     * @param  Cliente  $cliente  Instancia del cliente a eliminar
     * @return bool  True si se eliminó exitosamente
     */
    public function eliminar(Cliente $cliente): bool;

    /**
     * Verifica si ya existe un cliente con el mismo documento en el mismo usuario.
     * Permite excluir un ID para validaciones de actualización.
     *
     * @param  string    $numero     Número de documento a verificar
     * @param  int       $tipo       Código DIAN del tipo de documento
     * @param  int       $userId     ID del usuario autenticado
     * @param  int|null  $excluirId  ID del cliente a excluir de la búsqueda
     * @return bool  True si ya existe otro cliente con ese documento
     */
    public function existeDocumento(
        string $numero,
        int $tipo,
        int $userId,
        ?int $excluirId = null
    ): bool;
}
