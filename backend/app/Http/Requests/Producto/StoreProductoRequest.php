<?php

declare(strict_types=1);

namespace App\Http\Requests\Producto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para crear un producto en el catálogo.
 */
class StoreProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo'                      => ['required', 'string', 'max:30'],
            'nombre'                      => ['required', 'string', 'max:300'],
            'descripcion'                 => ['nullable', 'string', 'max:1000'],
            'unidad_medida_id'            => ['sometimes', 'string', 'max:5'],
            'tipo_item_identificacion_id' => ['sometimes', 'string', 'max:5'],
            'codigo_referencia'           => ['nullable', 'string', 'max:60'],
            'precio_unitario'             => ['required', 'numeric', 'min:0'],
            'porcentaje_descuento'        => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'porcentaje_iva'              => ['required', 'numeric', Rule::in([0, 5, 19])],
            'tribute_id'                  => ['sometimes', 'integer', Rule::in([1, 4, 6])],
            'activo'                      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required'          => 'El código del producto es obligatorio.',
            'nombre.required'          => 'El nombre del producto es obligatorio.',
            'precio_unitario.required' => 'El precio unitario es obligatorio.',
            'precio_unitario.min'      => 'El precio unitario no puede ser negativo.',
            'porcentaje_iva.required'  => 'La tarifa de IVA es obligatoria.',
            'porcentaje_iva.in'        => 'La tarifa de IVA debe ser 0, 5 o 19.',
        ];
    }
}
