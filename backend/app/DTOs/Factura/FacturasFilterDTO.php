<?php

declare(strict_types=1);

namespace App\DTOs\Factura;

/**
 * DTO para los filtros del listado de facturas.
 *
 * Encapsula los parámetros de búsqueda y paginación que llegan desde
 * el request HTTP hacia el FacturaRepository::paginar().
 *
 * Al usar un DTO en lugar de un array, el IDE puede autocompletar los
 * campos y el compilador detecta errores de nombre de filtro en tiempo
 * de desarrollo.
 *
 * Flujo de uso:
 *   Request (query params) → FacturasFilterDTO::fromArray() → FacturaService → FacturaRepository
 */
readonly class FacturasFilterDTO
{
    /**
     * Construye el DTO con los parámetros de filtrado.
     *
     * @param  string|null  $estado     Filtrar por estado: borrador | emitida | anulada
     * @param  int|null     $clienteId  Filtrar facturas de un cliente específico
     * @param  string|null  $fechaDesde Fecha mínima de creación (Y-m-d)
     * @param  string|null  $fechaHasta Fecha máxima de creación (Y-m-d)
     * @param  string|null  $busqueda   Texto libre: número de factura o CUFE parcial
     * @param  int          $perPage    Registros por página (default 15, máximo 100)
     */
    public function __construct(
        public readonly ?string $estado,
        public readonly ?int $clienteId,
        public readonly ?string $fechaDesde,
        public readonly ?string $fechaHasta,
        public readonly ?string $busqueda,
        public readonly int $perPage = 15,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Crea el DTO a partir de los query params del request.
     *
     * @param  array  $data  Array con los parámetros — generalmente $request->query()
     * @return static
     */
    public static function fromArray(array $data): static
    {
        // Limitar perPage a un máximo de 100 para evitar consultas abusivas
        $perPage = min((int) ($data['per_page'] ?? 15), 100);
        $perPage = max($perPage, 1); // Mínimo 1

        return new static(
            estado:     $data['estado'] ?? null,
            clienteId:  isset($data['cliente_id']) ? (int) $data['cliente_id'] : null,
            fechaDesde: $data['fecha_desde'] ?? null,
            fechaHasta: $data['fecha_hasta'] ?? null,
            busqueda:   $data['busqueda'] ?? null,
            perPage:    $perPage,
        );
    }

    // ── Conversión ────────────────────────────────────────────────────────────

    /**
     * Convierte el DTO a array para pasarlo al repositorio.
     * Omite los valores null para que el repositorio no aplique filtros vacíos.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $filtros = [];

        if ($this->estado !== null)     $filtros['estado']      = $this->estado;
        if ($this->clienteId !== null)  $filtros['cliente_id']  = $this->clienteId;
        if ($this->fechaDesde !== null) $filtros['fecha_desde'] = $this->fechaDesde;
        if ($this->fechaHasta !== null) $filtros['fecha_hasta'] = $this->fechaHasta;
        if ($this->busqueda !== null)   $filtros['busqueda']    = $this->busqueda;

        return $filtros;
    }

    // ── Métodos de consulta ───────────────────────────────────────────────────

    /**
     * Indica si el DTO tiene algún filtro activo (distinto al paginador).
     *
     * @return bool
     */
    public function tieneFiltros(): bool
    {
        return $this->estado !== null
            || $this->clienteId !== null
            || $this->fechaDesde !== null
            || $this->fechaHasta !== null
            || $this->busqueda !== null;
    }

    /**
     * Indica si hay un rango de fechas completo (desde y hasta).
     *
     * @return bool
     */
    public function tieneRangoFechas(): bool
    {
        return $this->fechaDesde !== null && $this->fechaHasta !== null;
    }
}
