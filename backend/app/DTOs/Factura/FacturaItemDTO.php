<?php

declare(strict_types=1);

namespace App\DTOs\Factura;

/**
 * DTO para un ítem (línea de detalle) de una factura.
 *
 * Transporta los datos de un ítem entre el Service y el Repository.
 * Todos los valores calculados (valor_descuento, valor_iva, subtotal, total)
 * se calculan al construir el DTO mediante calcularTotales(), garantizando
 * que los valores persisted sean siempre coherentes con cantidad y precio.
 *
 * Flujo de uso:
 *   array de ítems del request → FacturaItemDTO::fromArray() → acumular en CreateFacturaDTO
 *
 * Los valores calculados se obtienen llamando al método withTotalesCalculados()
 * que retorna una nueva instancia (inmutabilidad — readonly class).
 */
readonly class FacturaItemDTO
{
    /**
     * Construye el DTO con todos los campos de un ítem de factura.
     *
     * @param  int|null     $productoId               ID del producto del catálogo — null para conceptos libres
     * @param  string       $nombre                   Nombre del ítem en la factura
     * @param  string|null  $codigo                   Código del producto al momento de facturar
     * @param  string|null  $descripcion              Descripción adicional del ítem
     * @param  string       $unidadMedidaId           Código unidad medida DIAN
     * @param  string       $tipoItemIdentificacionId Tipo identificación ítem DIAN
     * @param  string|null  $codigoReferencia          Código de referencia EAN/GTIN
     * @param  float        $cantidad                 Cantidad del ítem (permite fracciones)
     * @param  float        $precioUnitario           Precio unitario sin descuento ni IVA
     * @param  float        $porcentajeDescuento      Porcentaje de descuento (0-100)
     * @param  float        $porcentajeIva            Tarifa de IVA: 0, 5 o 19
     * @param  int          $tributeId                Código impuesto DIAN: 1=IVA, 4=INC
     * @param  float        $valorDescuento           Valor calculado del descuento
     * @param  float        $valorIva                 Valor calculado del IVA
     * @param  float        $subtotal                 Base gravable: (cant × precio) - descuento
     * @param  float        $total                    Total ítem: subtotal + IVA
     */
    public function __construct(
        public readonly ?int $productoId,
        public readonly string $nombre,
        public readonly ?string $codigo,
        public readonly ?string $descripcion,
        public readonly string $unidadMedidaId,
        public readonly string $tipoItemIdentificacionId,
        public readonly ?string $codigoReferencia,
        public readonly float $cantidad,
        public readonly float $precioUnitario,
        public readonly float $porcentajeDescuento,
        public readonly float $porcentajeIva,
        public readonly int $tributeId,
        public readonly float $valorDescuento = 0.0,
        public readonly float $valorIva = 0.0,
        public readonly float $subtotal = 0.0,
        public readonly float $total = 0.0,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Crea el DTO a partir de un array y calcula automáticamente los totales.
     *
     * Este es el método de entrada preferido — crea el DTO y retorna
     * una instancia con todos los valores calculados listos para persistir.
     *
     * @param  array  $data  Datos del ítem desde el request validado
     * @return static  DTO con totales calculados
     */
    public static function fromArray(array $data): static
    {
        $instancia = new static(
            productoId:               isset($data['producto_id']) ? (int) $data['producto_id'] : null,
            nombre:                   $data['nombre'],
            codigo:                   $data['codigo'] ?? null,
            descripcion:              $data['descripcion'] ?? null,
            unidadMedidaId:           $data['unidad_medida_id'] ?? '94',
            tipoItemIdentificacionId: $data['tipo_item_identificacion_id'] ?? '999',
            codigoReferencia:         $data['codigo_referencia'] ?? null,
            cantidad:                 (float) $data['cantidad'],
            precioUnitario:           (float) $data['precio_unitario'],
            porcentajeDescuento:      (float) ($data['porcentaje_descuento'] ?? 0),
            porcentajeIva:            (float) $data['porcentaje_iva'],
            tributeId:                (int) ($data['tribute_id'] ?? 1),
        );

        return $instancia->withTotalesCalculados();
    }

    // ── Cálculo de totales ────────────────────────────────────────────────────

    /**
     * Retorna una nueva instancia del DTO con los totales calculados.
     *
     * Usa el patrón "wither" (with* method) para preservar la inmutabilidad:
     * en lugar de mutar la instancia, retorna una nueva con los valores calculados.
     *
     * Fórmulas:
     *   valorDescuento = cantidad × precioUnitario × (porcentajeDescuento / 100)
     *   subtotal       = (cantidad × precioUnitario) - valorDescuento
     *   valorIva       = subtotal × (porcentajeIva / 100)
     *   total          = subtotal + valorIva
     *
     * @return static  Nueva instancia con los cuatro campos calculados
     */
    public function withTotalesCalculados(): static
    {
        $precioBase     = round($this->cantidad * $this->precioUnitario, 2);
        $valorDescuento = round($precioBase * ($this->porcentajeDescuento / 100), 2);
        $subtotal       = round($precioBase - $valorDescuento, 2);
        $valorIva       = round($subtotal * ($this->porcentajeIva / 100), 2);
        $total          = round($subtotal + $valorIva, 2);

        return new static(
            productoId:               $this->productoId,
            nombre:                   $this->nombre,
            codigo:                   $this->codigo,
            descripcion:              $this->descripcion,
            unidadMedidaId:           $this->unidadMedidaId,
            tipoItemIdentificacionId: $this->tipoItemIdentificacionId,
            codigoReferencia:         $this->codigoReferencia,
            cantidad:                 $this->cantidad,
            precioUnitario:           $this->precioUnitario,
            porcentajeDescuento:      $this->porcentajeDescuento,
            porcentajeIva:            $this->porcentajeIva,
            tributeId:                $this->tributeId,
            valorDescuento:           $valorDescuento,
            valorIva:                 $valorIva,
            subtotal:                 $subtotal,
            total:                    $total,
        );
    }

    // ── Conversión ────────────────────────────────────────────────────────────

    /**
     * Convierte el DTO a array para el insert masivo del repositorio.
     * Nota: factura_id se agrega en el repositorio, no aquí.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'producto_id'                 => $this->productoId,
            'codigo'                      => $this->codigo,
            'nombre'                      => $this->nombre,
            'descripcion'                 => $this->descripcion,
            'unidad_medida_id'            => $this->unidadMedidaId,
            'tipo_item_identificacion_id' => $this->tipoItemIdentificacionId,
            'codigo_referencia'           => $this->codigoReferencia,
            'cantidad'                    => $this->cantidad,
            'precio_unitario'             => $this->precioUnitario,
            'porcentaje_descuento'        => $this->porcentajeDescuento,
            'valor_descuento'             => $this->valorDescuento,
            'porcentaje_iva'              => $this->porcentajeIva,
            'valor_iva'                   => $this->valorIva,
            'tribute_id'                  => $this->tributeId,
            'subtotal'                    => $this->subtotal,
            'total'                       => $this->total,
        ];
    }
}
