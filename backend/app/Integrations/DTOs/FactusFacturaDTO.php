<?php

declare(strict_types=1);

namespace App\Integrations\DTOs;

/**
 * DTO con la respuesta de Factus al emitir una factura electrónica.
 *
 * El FactusClient lo construye desde la respuesta del endpoint de emisión
 * y el FacturaService lo usa para actualizar la factura local vía el repositorio.
 *
 * Campos clave retornados por Factus:
 *   - id:          ID numérico de la factura en el sistema Factus
 *   - numero:      Número de factura asignado (ej: "SETP990000001")
 *   - cufe:        Código Único de Factura Electrónica — identifica la factura ante la DIAN
 *   - qr:          Contenido textual del código QR de la factura
 *   - pdfBase64:   PDF de la factura en Base64 — para descarga inmediata
 *   - rawResponse: Payload completo para auditoría y soporte
 */
readonly class FactusFacturaDTO
{
    /**
     * @param  int         $id           ID de la factura en Factus
     * @param  string      $numero       Número de factura (prefijo + consecutivo)
     * @param  string      $cufe         Código Único de Factura Electrónica
     * @param  string|null $qr           Contenido del código QR
     * @param  string|null $pdfBase64    PDF en Base64
     * @param  array       $rawResponse  Payload completo de la respuesta
     */
    public function __construct(
        public readonly int $id,
        public readonly string $numero,
        public readonly string $cufe,
        public readonly ?string $qr,
        public readonly ?string $pdfBase64,
        public readonly array $rawResponse,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Construye el DTO desde el payload JSON retornado por Factus al emitir.
     *
     * La API de Factus retorna la factura dentro de `data.bill` o directamente
     * en `data`. Este método maneja ambos formatos.
     *
     * @param  array  $response  Payload JSON completo de la respuesta exitosa
     * @return static
     * @throws \InvalidArgumentException Si la respuesta no tiene los campos requeridos
     */
    public static function fromResponse(array $response): static
    {
        // Extraer el objeto bill — puede estar en data.bill o directamente en data
        $bill = $response['data']['bill'] ?? $response['data'] ?? $response;

        if (empty($bill['id']) || empty($bill['cufe'])) {
            throw new \InvalidArgumentException(
                'La respuesta de Factus no contiene los campos requeridos (id, cufe).'
            );
        }

        return new static(
            id:          (int) $bill['id'],
            numero:      $bill['number'] ?? $bill['numero'] ?? '',
            cufe:        $bill['cufe'],
            qr:          $bill['qr_image'] ?? $bill['qr'] ?? null,
            pdfBase64:   $bill['pdf_base64_encoded'] ?? $bill['pdf_base64'] ?? null,
            rawResponse: $response,
        );
    }

    // ── Conversión ────────────────────────────────────────────────────────────

    /**
     * Convierte el DTO al array esperado por FacturaRepository::marcarComoEmitida().
     *
     * @return array<string, mixed>
     */
    public function toRepositoryArray(): array
    {
        return [
            'factus_id'         => $this->id,
            'factus_numero'     => $this->numero,
            'factus_cufe'       => $this->cufe,
            'factus_qr'         => $this->qr,
            'factus_pdf_base64' => $this->pdfBase64,
            'factus_respuesta'  => $this->rawResponse,
        ];
    }
}
