<?php

declare(strict_types=1);

namespace App\DTOs\Factura;

/**
 * DTO para crear una factura electrónica.
 *
 * Agrega todos los datos del encabezado de la factura junto con el array
 * de ítems (FacturaItemDTO[]). Los totales del encabezado se calculan
 * sumando los valores de cada ítem — nunca se confía en los totales
 * enviados por el cliente.
 *
 * Flujo de uso:
 *   FormRequest::validated() → CreateFacturaDTO::fromArray() → FacturaService::crear()
 *      → FacturaRepository::crear() (datos de encabezado)
 *      → FacturaRepository::crearItems() (ítems individuales)
 */
readonly class CreateFacturaDTO
{
    /**
     * Construye el DTO de factura con su colección de ítems.
     *
     * @param  int               $userId             ID del usuario autenticado
     * @param  int               $clienteId          ID del cliente receptor
     * @param  int               $numberingRangeId   ID del rango de numeración en Factus
     * @param  string|null       $prefijo            Prefijo del rango de numeración
     * @param  int               $paymentForm        1=Contado | 2=Crédito
     * @param  int               $paymentMethodCode  Código método de pago DIAN
     * @param  string|null       $paymentDueDate     Fecha vencimiento — solo para crédito (Y-m-d)
     * @param  string|null       $observaciones      Observaciones adicionales de la factura
     * @param  FacturaItemDTO[]  $items              Array de ítems con totales ya calculados
     * @param  float             $subtotal           Suma de subtotales de todos los ítems
     * @param  float             $totalDescuento     Suma de descuentos de todos los ítems
     * @param  float             $totalIva           Suma de IVA de todos los ítems
     * @param  float             $total              Total a pagar: subtotal - descuentos + IVA
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $clienteId,
        public readonly int $numberingRangeId,
        public readonly ?string $prefijo,
        public readonly int $paymentForm,
        public readonly int $paymentMethodCode,
        public readonly ?string $paymentDueDate,
        public readonly ?string $observaciones,
        public readonly array $items,
        public readonly float $subtotal,
        public readonly float $totalDescuento,
        public readonly float $totalIva,
        public readonly float $total,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Crea el DTO a partir de datos validados, instanciando los ítems
     * y calculando automáticamente todos los totales del encabezado.
     *
     * Los totales del request se ignoran — se recalculan desde los ítems
     * para garantizar integridad y evitar manipulación del cliente.
     *
     * @param  array  $data    Datos validados del request
     * @param  int    $userId  ID del usuario autenticado
     * @return static  DTO completo con totales calculados
     */
    public static function fromArray(array $data, int $userId): static
    {
        // Convertir cada ítem del request en un FacturaItemDTO con totales calculados
        $items = array_map(
            fn (array $itemData): FacturaItemDTO => FacturaItemDTO::fromArray($itemData),
            $data['items'] ?? []
        );

        // Calcular totales del encabezado sumando los valores de los ítems
        $subtotal       = round(array_sum(array_map(fn ($i) => $i->subtotal, $items)), 2);
        $totalDescuento = round(array_sum(array_map(fn ($i) => $i->valorDescuento, $items)), 2);
        $totalIva       = round(array_sum(array_map(fn ($i) => $i->valorIva, $items)), 2);
        $total          = round($subtotal + $totalIva, 2);

        return new static(
            userId:            $userId,
            clienteId:         (int) $data['cliente_id'],
            numberingRangeId:  (int) $data['numbering_range_id'],
            prefijo:           $data['prefijo'] ?? null,
            paymentForm:       (int) $data['payment_form'],
            paymentMethodCode: (int) $data['payment_method_code'],
            paymentDueDate:    $data['payment_due_date'] ?? null,
            observaciones:     $data['observaciones'] ?? null,
            items:             $items,
            subtotal:          $subtotal,
            totalDescuento:    $totalDescuento,
            totalIva:          $totalIva,
            total:             $total,
        );
    }

    // ── Conversión ────────────────────────────────────────────────────────────

    /**
     * Convierte el encabezado de la factura a array para el repositorio.
     * No incluye los ítems — se pasan por separado a crearItems().
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'             => $this->userId,
            'cliente_id'          => $this->clienteId,
            'numbering_range_id'  => $this->numberingRangeId,
            'prefijo'             => $this->prefijo,
            'payment_form'        => $this->paymentForm,
            'payment_method_code' => $this->paymentMethodCode,
            'payment_due_date'    => $this->paymentDueDate,
            'observaciones'       => $this->observaciones,
            'subtotal'            => $this->subtotal,
            'total_descuento'     => $this->totalDescuento,
            'total_iva'           => $this->totalIva,
            'total'               => $this->total,
        ];
    }

    /**
     * Convierte los ítems a un array de arrays para el insert masivo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function itemsToArray(): array
    {
        return array_map(
            fn (FacturaItemDTO $item): array => $item->toArray(),
            $this->items
        );
    }

    // ── Métodos de consulta ───────────────────────────────────────────────────

    /**
     * Indica si la factura tiene al menos un ítem.
     *
     * @return bool
     */
    public function tieneItems(): bool
    {
        return count($this->items) > 0;
    }

    /**
     * Indica si la forma de pago es crédito (requiere fecha de vencimiento).
     *
     * @return bool
     */
    public function esCredito(): bool
    {
        return $this->paymentForm === 2;
    }
}
