<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de gestión de clientes.
 *
 * Contiene toda la lógica de negocio relacionada con clientes:
 *   - Listar/paginar con filtros opcionales
 *   - Obtener todos los clientes activos (para selectores del frontend)
 *   - Buscar por ID con validación de pertenencia al usuario
 *   - Crear, actualizar y eliminar (soft delete)
 *
 * Siempre verifica que el recurso pertenezca al usuario autenticado.
 * Lanza ClienteNotFoundException si el cliente no existe o no pertenece al usuario.
 *
 * Flujo estándar:
 *   Controller → ClienteService → ClienteRepositoryInterface → DB
 */

use App\DTOs\Cliente\CreateClienteDTO;
use App\Exceptions\ClienteNotFoundException;
use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClienteService
{
    /**
     * @param  ClienteRepositoryInterface  $clienteRepository  Repositorio de clientes inyectado por IoC
     */
    public function __construct(
        private readonly ClienteRepositoryInterface $clienteRepository,
    ) {}

    // ── Consultas ─────────────────────────────────────────────────────────────

    /**
     * Retorna los clientes del usuario paginados con filtros opcionales.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  Filtros: busqueda (string), tipo_persona ('J'|'N'), activo (bool)
     * @param  int    $perPage  Registros por página
     * @return LengthAwarePaginator<Cliente>
     */
    public function listar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->clienteRepository->paginar($userId, $filtros, $perPage);
    }

    /**
     * Retorna todos los clientes activos del usuario sin paginar.
     * Útil para cargar selectores de clientes al crear facturas.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return Collection<int, Cliente>
     */
    public function todos(int $userId): Collection
    {
        return $this->clienteRepository->todos($userId);
    }

    /**
     * Busca un cliente por su ID verificando pertenencia al usuario.
     *
     * @param  int  $id      ID del cliente a buscar
     * @param  int  $userId  ID del usuario autenticado
     * @return Cliente  El cliente encontrado
     * @throws ClienteNotFoundException  Si no existe o no pertenece al usuario
     */
    public function buscar(int $id, int $userId): Cliente
    {
        $cliente = $this->clienteRepository->buscarPorId($id, $userId);

        if ($cliente === null) {
            throw ClienteNotFoundException::conId($id);
        }

        return $cliente;
    }

    // ── Mutaciones ────────────────────────────────────────────────────────────

    /**
     * Crea un nuevo cliente con los datos validados.
     * Construye el DTO desde el array y delega la persistencia al repositorio.
     *
     * @param  array  $data    Datos validados del FormRequest
     * @param  int    $userId  ID del usuario autenticado (propietario del cliente)
     * @return Cliente  El cliente recién creado
     */
    public function crear(array $data, int $userId): Cliente
    {
        $dto = CreateClienteDTO::fromArray($data, $userId);

        return $this->clienteRepository->crear($dto);
    }

    /**
     * Actualiza los datos de un cliente existente.
     * Primero verifica que el cliente exista y pertenezca al usuario.
     *
     * @param  int    $id      ID del cliente a actualizar
     * @param  array  $data    Datos validados del FormRequest
     * @param  int    $userId  ID del usuario autenticado
     * @return Cliente  El cliente con los datos actualizados
     * @throws ClienteNotFoundException  Si no existe o no pertenece al usuario
     */
    public function actualizar(int $id, array $data, int $userId): Cliente
    {
        $cliente = $this->buscar($id, $userId);
        $dto     = CreateClienteDTO::fromArray($data, $userId);

        return $this->clienteRepository->actualizar($cliente, $dto);
    }

    /**
     * Elimina (soft delete) un cliente del usuario.
     * Primero verifica que el cliente exista y pertenezca al usuario.
     *
     * @param  int  $id      ID del cliente a eliminar
     * @param  int  $userId  ID del usuario autenticado
     * @return bool  True si la eliminación fue exitosa
     * @throws ClienteNotFoundException  Si no existe o no pertenece al usuario
     */
    public function eliminar(int $id, int $userId): bool
    {
        $cliente = $this->buscar($id, $userId);

        return $this->clienteRepository->eliminar($cliente);
    }
}
