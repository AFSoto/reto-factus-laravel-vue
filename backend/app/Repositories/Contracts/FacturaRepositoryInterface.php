<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * Contrato del repositorio de facturas.
 *
 * Define todas las operaciones de persistencia sobre la entidad Factura.
 * Incluye métodos específicos del dominio de facturación electrónica:
 * transiciones de estado (emitir, anular) y métricas para el dashboard.
 *
 * Convención de filtros para paginar():
 *   - 'estado'      (string): borrador | emitida | anulada
 *   - 'cliente_id'  (int): filtrar por cliente
 *   - 'fecha_desde' (string Y-m-d): rango de fecha inicio
 *   - 'fecha_hasta' (string Y-m-d): rango de fecha fin
 *   - 'busqueda'    (string): número de factura o CUFE parcial
 */

use App\Models\Factura;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface FacturaRepositoryInterface
{
    /**
     * Retorna las facturas del usuario paginadas con filtros opcionales.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  Filtros opcionales: estado, cliente_id, fecha_desde, fecha_hasta, busqueda
     * @param  int    $perPage  Registros por página (default 15)
     * @return LengthAwarePaginator<Factura>
     */
    public function paginar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Busca una factura por su ID verificando pertenencia al usuario.
     * Carga las relaciones cliente e items automáticamente.
     *
     * @param  int  $id      ID de la factura a buscar
     * @param  int  $userId  ID del usuario autenticado
     * @return Factura|null  La factura con relaciones o null si no existe
     */
    public function buscarPorId(int $id, int $userId): ?Factura;

    /**
     * Busca una factura por el ID asignado por la API de Factus.
     *
     * @param  int  $factusId  ID retornado por Factus al emitir la factura
     * @return Factura|null
     */
    public function buscarPorFactusId(int $factusId): ?Factura;

    /**
     * Crea una nueva factura en estado borrador.
     * Los ítems deben crearse por separado mediante crearItems().
     *
     * @param  array{
     *     user_id: int,
     *     cliente_id: int,
     *     numbering_range_id: int,
     *     prefijo?: string|null,
     *     numero: int,
     *     numero_completo?: string|null,
     *     payment_form: int,
     *     payment_method_code: int,
     *     payment_due_date?: string|null,
     *     observaciones?: string|null,
     *     subtotal: float,
     *     total_descuento: float,
     *     total_iva: float,
     *     total: float,
     *     estado?: string
     * }  $data  Datos de la factura a crear
     * @return Factura  La factura recién creada
     */
    public function crear(array $data): Factura;

    /**
     * Crea los ítems de una factura de forma masiva.
     * Reemplaza todos los ítems existentes si se llama sobre una factura con ítems.
     *
     * @param  Factura  $factura  Factura a la que pertenecen los ítems
     * @param  array    $items    Array de arrays con los datos de cada ítem
     * @return void
     */
    public function crearItems(Factura $factura, array $items): void;

    /**
     * Actualiza los datos de una factura existente (solo borradores).
     *
     * @param  Factura  $factura  Instancia de la factura a actualizar
     * @param  array    $data     Campos a modificar (misma forma que crear())
     * @return Factura  La factura con los datos actualizados
     */
    public function actualizar(Factura $factura, array $data): Factura;

    /**
     * Elimina (soft delete) una factura en estado borrador.
     *
     * @param  Factura  $factura  Instancia de la factura a eliminar
     * @return bool  True si se eliminó exitosamente
     */
    public function eliminar(Factura $factura): bool;

    /**
     * Marca una factura como emitida con los datos retornados por Factus.
     * Actualiza estado, factus_id, factus_numero, factus_cufe, factus_qr y emitida_en.
     *
     * @param  Factura  $factura     Factura que fue aprobada por Factus
     * @param  array{
     *     factus_id: int,
     *     factus_numero: string,
     *     factus_cufe: string,
     *     factus_qr?: string|null,
     *     factus_pdf_base64?: string|null,
     *     factus_respuesta: array
     * }  $datosFactus  Datos de la respuesta de la API Factus
     * @return Factura  La factura actualizada en estado 'emitida'
     */
    public function marcarComoEmitida(Factura $factura, array $datosFactus): Factura;

    /**
     * Marca una factura como anulada ante la DIAN.
     * Actualiza estado, anulada_en y motivo_anulacion.
     *
     * @param  Factura  $factura  Factura a anular
     * @param  string   $motivo   Motivo de anulación ingresado por el usuario
     * @return Factura  La factura actualizada en estado 'anulada'
     */
    public function marcarComoAnulada(Factura $factura, string $motivo): Factura;

    /**
     * Retorna el conteo de facturas agrupadas por estado para el dashboard.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return array{borrador: int, emitida: int, anulada: int}
     */
    public function contarPorEstado(int $userId): array;

    /**
     * Retorna el total facturado (facturas emitidas) en un mes específico.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @param  int  $mes     Número de mes (1-12)
     * @param  int  $anio    Año de cuatro dígitos
     * @return float  Suma del campo 'total' de facturas emitidas en ese período
     */
    public function totalFacturadoMes(int $userId, int $mes, int $anio): float;

    /**
     * Retorna las N facturas más recientes para el widget del dashboard.
     * Incluye la relación cliente para mostrar el nombre receptor.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @param  int  $limite  Cantidad máxima de facturas a retornar (default 5)
     * @return Collection<int, Factura>
     */
    public function facturasRecientes(int $userId, int $limite = 5): Collection;
}
