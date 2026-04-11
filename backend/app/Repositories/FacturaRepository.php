<?php

declare(strict_types=1);

namespace App\Repositories;

/**
 * Implementación Eloquent del repositorio de facturas.
 *
 * Única capa autorizada a usar Eloquent sobre las tablas 'facturas' y
 * 'factura_items'. Implementa FacturaRepositoryInterface.
 *
 * Responsabilidades especiales de este repositorio frente a los otros:
 *   - Maneja la creación masiva de ítems (crearItems).
 *   - Ejecuta transiciones de estado (marcarComoEmitida, marcarComoAnulada)
 *     que deben ser atómicas (usan update directo, no fill+save para evitar
 *     cargar relaciones innecesariamente).
 *   - Provee métricas agregadas para el dashboard.
 */

use App\DTOs\Factura\CreateFacturaDTO;
use App\Models\Factura;
use App\Models\FacturaItem;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FacturaRepository implements FacturaRepositoryInterface
{
    /**
     * Retorna las facturas del usuario paginadas con filtros opcionales.
     *
     * Carga la relación 'cliente' de forma eager para evitar N+1 en el listado.
     *
     * @param  int    $userId   ID del usuario autenticado
     * @param  array  $filtros  estado, cliente_id, fecha_desde, fecha_hasta, busqueda
     * @param  int    $perPage  Registros por página
     * @return LengthAwarePaginator<Factura>
     */
    public function paginar(int $userId, array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Factura::query()
            ->with('cliente:id,razon_social,primer_nombre,primer_apellido,numero_documento')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Filtro por estado de la factura
        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        // Filtro por cliente específico
        if (!empty($filtros['cliente_id'])) {
            $query->where('cliente_id', (int) $filtros['cliente_id']);
        }

        // Rango de fechas — basado en created_at
        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
        }

        // Búsqueda por número de factura o CUFE parcial
        if (!empty($filtros['busqueda'])) {
            $termino = '%' . $filtros['busqueda'] . '%';
            $query->where(function ($q) use ($termino): void {
                $q->where('numero_completo', 'like', $termino)
                    ->orWhere('factus_numero', 'like', $termino)
                    ->orWhere('factus_cufe', 'like', $termino);
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Busca una factura por ID con sus relaciones cargadas.
     * Carga cliente e items para el detalle de la factura.
     *
     * @param  int  $id      ID de la factura
     * @param  int  $userId  ID del usuario autenticado
     * @return Factura|null
     */
    public function buscarPorId(int $id, int $userId): ?Factura
    {
        return Factura::query()
            ->with([
                'cliente',
                'items',
                'items.producto:id,codigo,nombre',
            ])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Busca una factura por el ID asignado por la API de Factus.
     *
     * @param  int  $factusId  ID de Factus
     * @return Factura|null
     */
    public function buscarPorFactusId(int $factusId): ?Factura
    {
        return Factura::query()
            ->where('factus_id', $factusId)
            ->first();
    }

    /**
     * Crea y persiste una nueva factura en estado borrador desde el DTO.
     * El número y numero_completo los asigna el Service consultando el rango en Factus.
     *
     * @param  CreateFacturaDTO  $dto            DTO con encabezado e ítems de la factura
     * @param  int               $numero         Número consecutivo asignado por el Service
     * @param  string|null       $numeroCompleto Prefijo + número formateado (ej: SETP990000001)
     * @return Factura
     */
    public function crear(CreateFacturaDTO $dto, int $numero, ?string $numeroCompleto): Factura
    {
        return Factura::create(array_merge($dto->toArray(), [
            'numero'          => $numero,
            'numero_completo' => $numeroCompleto,
            'estado'          => Factura::ESTADO_BORRADOR,
        ]));
    }

    /**
     * Crea los ítems de una factura en una sola operación de insert masivo.
     * Primero elimina los ítems existentes de la factura, luego inserta los nuevos.
     * Los timestamps se asignan manualmente porque usamos insert() directo.
     *
     * @param  Factura  $factura  Factura propietaria de los ítems
     * @param  array    $items    Array de arrays con los datos de cada ítem
     * @return void
     */
    public function crearItems(Factura $factura, array $items): void
    {
        // Eliminar ítems previos antes de reinsertarlos
        FacturaItem::where('factura_id', $factura->id)->delete();

        $ahora = Carbon::now();

        // Asignamos factura_id y timestamps a cada ítem
        $itemsConId = array_map(function (array $item) use ($factura, $ahora): array {
            return array_merge($item, [
                'factura_id' => $factura->id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }, $items);

        FacturaItem::insert($itemsConId);
    }

    /**
     * Actualiza los datos de una factura borrador desde el DTO.
     *
     * @param  Factura           $factura        Instancia a actualizar
     * @param  CreateFacturaDTO  $dto            DTO con los nuevos datos validados
     * @param  int               $numero         Número consecutivo (puede cambiar al cambiar rango)
     * @param  string|null       $numeroCompleto Prefijo + número formateado
     * @return Factura
     */
    public function actualizar(Factura $factura, CreateFacturaDTO $dto, int $numero, ?string $numeroCompleto): Factura
    {
        $factura->update(array_merge($dto->toArray(), [
            'numero'          => $numero,
            'numero_completo' => $numeroCompleto,
        ]));

        return $factura->refresh();
    }

    /**
     * Soft-delete de una factura.
     *
     * @param  Factura  $factura  Instancia a eliminar
     * @return bool
     */
    public function eliminar(Factura $factura): bool
    {
        return (bool) $factura->delete();
    }

    /**
     * Transición de estado: borrador → emitida.
     * Actualiza todos los campos de la respuesta Factus en una sola query.
     *
     * @param  Factura  $factura     Factura aprobada por Factus
     * @param  array    $datosFactus Datos retornados por la API Factus
     * @return Factura
     */
    public function marcarComoEmitida(Factura $factura, array $datosFactus): Factura
    {
        $factura->update([
            'estado'            => Factura::ESTADO_EMITIDA,
            'factus_id'         => $datosFactus['factus_id'],
            'factus_numero'     => $datosFactus['factus_numero'],
            'factus_cufe'       => $datosFactus['factus_cufe'],
            'factus_qr'         => $datosFactus['factus_qr'] ?? null,
            'factus_pdf_base64' => $datosFactus['factus_pdf_base64'] ?? null,
            'factus_respuesta'  => $datosFactus['factus_respuesta'],
            'emitida_en'        => Carbon::now(),
        ]);

        return $factura->refresh();
    }

    /**
     * Transición de estado: emitida → anulada.
     * Registra el momento de anulación y el motivo proporcionado.
     *
     * @param  Factura  $factura  Factura a anular
     * @param  string   $motivo   Motivo de anulación
     * @return Factura
     */
    public function marcarComoAnulada(Factura $factura, string $motivo): Factura
    {
        $factura->update([
            'estado'           => Factura::ESTADO_ANULADA,
            'anulada_en'       => Carbon::now(),
            'motivo_anulacion' => $motivo,
        ]);

        return $factura->refresh();
    }

    /**
     * Retorna el conteo de facturas por estado para el widget del dashboard.
     *
     * Usa una sola query con GROUP BY para eficiencia.
     * Siempre retorna los tres estados aunque alguno tenga cero.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @return array{borrador: int, emitida: int, anulada: int}
     */
    public function contarPorEstado(int $userId): array
    {
        $conteos = Factura::query()
            ->where('user_id', $userId)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->toArray();

        // Garantizamos que los tres estados siempre están presentes
        return [
            Factura::ESTADO_BORRADOR => (int) ($conteos[Factura::ESTADO_BORRADOR] ?? 0),
            Factura::ESTADO_EMITIDA  => (int) ($conteos[Factura::ESTADO_EMITIDA] ?? 0),
            Factura::ESTADO_ANULADA  => (int) ($conteos[Factura::ESTADO_ANULADA] ?? 0),
        ];
    }

    /**
     * Retorna el total facturado en un mes y año específicos.
     * Solo suma facturas en estado 'emitida'.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @param  int  $mes     Número de mes (1-12)
     * @param  int  $anio    Año de cuatro dígitos
     * @return float
     */
    public function totalFacturadoMes(int $userId, int $mes, int $anio): float
    {
        return (float) Factura::query()
            ->where('user_id', $userId)
            ->emitidas()
            ->whereMonth('emitida_en', $mes)
            ->whereYear('emitida_en', $anio)
            ->sum('total');
    }

    /**
     * Retorna las N facturas más recientes con el cliente cargado.
     * Usada en el widget "Actividad reciente" del dashboard.
     *
     * @param  int  $userId  ID del usuario autenticado
     * @param  int  $limite  Máximo de facturas a retornar
     * @return Collection<int, Factura>
     */
    public function facturasRecientes(int $userId, int $limite = 5): Collection
    {
        return Factura::query()
            ->with('cliente:id,razon_social,primer_nombre,primer_apellido')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limite)
            ->get();
    }
}
