<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de un ítem (línea de detalle) de factura.
 * Expone el snapshot del producto con todos los valores calculados.
 */
class FacturaItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'producto_id'                 => $this->producto_id,
            'codigo'                      => $this->codigo,
            'nombre'                      => $this->nombre,
            'descripcion'                 => $this->descripcion,
            'unidad_medida_id'            => $this->unidad_medida_id,
            'tipo_item_identificacion_id' => $this->tipo_item_identificacion_id,
            'codigo_referencia'           => $this->codigo_referencia,
            'cantidad'                    => $this->cantidad,
            'precio_unitario'             => $this->precio_unitario,
            'porcentaje_descuento'        => $this->porcentaje_descuento,
            'valor_descuento'             => $this->valor_descuento,
            'porcentaje_iva'              => $this->porcentaje_iva,
            'valor_iva'                   => $this->valor_iva,
            'tribute_id'                  => $this->tribute_id,
            'subtotal'                    => $this->subtotal,
            'total'                       => $this->total,
        ];
    }
}
