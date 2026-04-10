<?php

declare(strict_types=1);

namespace App\DTOs\Producto;

/**
 * DTO para crear o actualizar un producto.
 *
 * Transporta los datos validados del catálogo desde el Service al Repository.
 * Clase readonly — inmutable por diseño para garantizar integridad en tránsito.
 *
 * Flujo de uso:
 *   Request → FormRequest::validated() → CreateProductoDTO::fromArray() → ProductoRepository::crear()
 */
readonly class CreateProductoDTO
{
    /**
     * Construye el DTO con todos los campos del producto.
     *
     * @param  int          $userId                    ID del usuario propietario del catálogo
     * @param  string       $codigo                    Código interno único del producto
     * @param  string       $nombre                    Nombre del producto o servicio
     * @param  string|null  $descripcion               Descripción detallada opcional
     * @param  string       $unidadMedidaId            Código unidad de medida DIAN (default '94'=Unidad)
     * @param  string       $tipoItemIdentificacionId  Tipo identificación ítem DIAN (default '999')
     * @param  string|null  $codigoReferencia           Código EAN, GTIN u otro código de referencia externo
     * @param  float        $precioUnitario            Precio base sin impuestos
     * @param  float        $porcentajeDescuento       Porcentaje de descuento por defecto (0-100)
     * @param  float        $porcentajeIva             Tarifa de IVA: 0, 5 o 19
     * @param  int          $tributeId                 Código impuesto DIAN: 1=IVA, 4=INC
     * @param  bool         $activo                    Estado activo del producto
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly ?string $descripcion,
        public readonly string $unidadMedidaId,
        public readonly string $tipoItemIdentificacionId,
        public readonly ?string $codigoReferencia,
        public readonly float $precioUnitario,
        public readonly float $porcentajeDescuento,
        public readonly float $porcentajeIva,
        public readonly int $tributeId,
        public readonly bool $activo = true,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Crea el DTO a partir de un array de datos ya validados.
     *
     * @param  array  $data    Datos validados del producto
     * @param  int    $userId  ID del usuario autenticado
     * @return static
     */
    public static function fromArray(array $data, int $userId): static
    {
        return new static(
            userId:                   $userId,
            codigo:                   $data['codigo'],
            nombre:                   $data['nombre'],
            descripcion:              $data['descripcion'] ?? null,
            unidadMedidaId:           $data['unidad_medida_id'] ?? '94',
            tipoItemIdentificacionId: $data['tipo_item_identificacion_id'] ?? '999',
            codigoReferencia:         $data['codigo_referencia'] ?? null,
            precioUnitario:           (float) $data['precio_unitario'],
            porcentajeDescuento:      (float) ($data['porcentaje_descuento'] ?? 0),
            porcentajeIva:            (float) $data['porcentaje_iva'],
            tributeId:                (int) ($data['tribute_id'] ?? 1),
            activo:                   (bool) ($data['activo'] ?? true),
        );
    }

    // ── Conversión ────────────────────────────────────────────────────────────

    /**
     * Convierte el DTO a array para persistir mediante el repositorio.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'                     => $this->userId,
            'codigo'                      => $this->codigo,
            'nombre'                      => $this->nombre,
            'descripcion'                 => $this->descripcion,
            'unidad_medida_id'            => $this->unidadMedidaId,
            'tipo_item_identificacion_id' => $this->tipoItemIdentificacionId,
            'codigo_referencia'           => $this->codigoReferencia,
            'precio_unitario'             => $this->precioUnitario,
            'porcentaje_descuento'        => $this->porcentajeDescuento,
            'porcentaje_iva'              => $this->porcentajeIva,
            'tribute_id'                  => $this->tributeId,
            'activo'                      => $this->activo,
        ];
    }

    // ── Métodos de consulta ───────────────────────────────────────────────────

    /**
     * Indica si el producto aplica algún impuesto IVA.
     *
     * @return bool
     */
    public function tieneIva(): bool
    {
        return $this->porcentajeIva > 0;
    }

    /**
     * Retorna el precio final con IVA incluido.
     *
     * @return float
     */
    public function precioConIva(): float
    {
        return round($this->precioUnitario * (1 + ($this->porcentajeIva / 100)), 2);
    }
}
