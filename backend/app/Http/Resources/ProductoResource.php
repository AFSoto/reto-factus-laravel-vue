<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource del modelo Producto.
 * Incluye precio_con_iva calculado para mostrar directamente en el frontend.
 */
class ProductoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'codigo'                      => $this->codigo,
            'nombre'                      => $this->nombre,
            'descripcion'                 => $this->descripcion,
            'unidad_medida_id'            => $this->unidad_medida_id,
            'tipo_item_identificacion_id' => $this->tipo_item_identificacion_id,
            'codigo_referencia'           => $this->codigo_referencia,
            'precio_unitario'             => $this->precio_unitario,
            'precio_con_iva'              => $this->precioConIva(),
            'porcentaje_descuento'        => $this->porcentaje_descuento,
            'porcentaje_iva'              => $this->porcentaje_iva,
            'tribute_id'                  => $this->tribute_id,
            'activo'                      => $this->activo,
            'created_at'                  => $this->created_at,
            'updated_at'                  => $this->updated_at,
        ];
    }
}
