<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource del modelo Factura.
 *
 * Incluye:
 *   - Etiquetas legibles de estado y forma de pago para el frontend
 *   - Flags de negocio (es_editable, puede_emitirse, puede_anularse)
 *   - Relaciones cliente e items cargadas condicionalmente con whenLoaded()
 *   - PDF Base64 excluido cuando es null para no inflar respuestas de listado
 */
class FacturaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'numero'              => $this->numero,
            'numero_completo'     => $this->numero_completo,
            'prefijo'             => $this->prefijo,
            'numbering_range_id'  => $this->numbering_range_id,
            'payment_form'        => $this->payment_form,
            'payment_form_label'  => $this->payment_form === 1 ? 'Contado' : 'Crédito',
            'payment_method_code' => $this->payment_method_code,
            'payment_due_date'    => $this->payment_due_date?->format('Y-m-d'),
            'observaciones'       => $this->observaciones,
            'subtotal'            => $this->subtotal,
            'total_descuento'     => $this->total_descuento,
            'total_iva'           => $this->total_iva,
            'total'               => $this->total,
            'estado'              => $this->estado,
            'estado_label'        => $this->estado_label,
            'estado_color'        => $this->estado_color,
            'es_editable'         => $this->esEditable(),
            'puede_emitirse'      => $this->esBorrador(),
            'puede_anularse'      => $this->puedeAnularse(),
            'factus_id'           => $this->factus_id,
            'factus_numero'       => $this->factus_numero,
            'factus_cufe'         => $this->factus_cufe,
            'factus_qr'           => $this->factus_qr,
            'factus_pdf_base64'   => $this->when($this->factus_pdf_base64 !== null, $this->factus_pdf_base64),
            'emitida_en'          => $this->emitida_en,
            'anulada_en'          => $this->anulada_en,
            'motivo_anulacion'    => $this->motivo_anulacion,
            'cliente'             => ClienteResource::make($this->whenLoaded('cliente')),
            'items'               => FacturaItemResource::collection($this->whenLoaded('items')),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
