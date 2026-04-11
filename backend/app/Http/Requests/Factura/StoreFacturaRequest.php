<?php

declare(strict_types=1);

namespace App\Http\Requests\Factura;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para crear una factura borrador con sus ítems.
 *
 * Valida tanto el encabezado de la factura como el array de ítems.
 * Los totales del encabezado NO se validan aquí — se recalculan en el DTO
 * para garantizar integridad y evitar manipulación desde el cliente.
 */
class StoreFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Encabezado de la factura ──────────────────────────────────────
            'cliente_id'          => ['required', 'integer', 'exists:clientes,id'],
            'numbering_range_id'  => ['required', 'integer'],
            'prefijo'             => ['nullable', 'string', 'max:10'],
            'payment_form'        => ['required', 'integer', Rule::in([1, 2])],
            'payment_method_code' => ['required', 'integer', Rule::in([10, 20, 42, 48, 49])],
            'payment_due_date'    => ['required_if:payment_form,2', 'nullable', 'date', 'after_or_equal:today'],
            'observaciones'       => ['nullable', 'string', 'max:500'],

            // ── Ítems de la factura ────────────────────────────────────────────
            'items'               => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'items.*.codigo'      => ['required', 'string', 'max:30'],
            'items.*.nombre'      => ['required', 'string', 'max:300'],
            'items.*.descripcion' => ['nullable', 'string', 'max:1000'],
            'items.*.unidad_medida_id'            => ['required', 'string', 'max:5'],
            'items.*.tipo_item_identificacion_id' => ['required', 'string', 'max:5'],
            'items.*.codigo_referencia'           => ['nullable', 'string', 'max:60'],
            'items.*.cantidad'                    => ['required', 'numeric', 'min:0.001'],
            'items.*.precio_unitario'             => ['required', 'numeric', 'min:0'],
            'items.*.porcentaje_descuento'        => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'items.*.porcentaje_iva'              => ['required', 'numeric', Rule::in([0, 5, 19])],
            'items.*.tribute_id'                  => ['required', 'integer', Rule::in([1, 4, 6])],
        ];
    }

    public function messages(): array
    {
        return [
            'cliente_id.required'          => 'El cliente es obligatorio.',
            'cliente_id.exists'            => 'El cliente seleccionado no existe.',
            'numbering_range_id.required'  => 'El rango de numeración es obligatorio.',
            'payment_form.required'        => 'La forma de pago es obligatoria.',
            'payment_form.in'              => 'La forma de pago debe ser 1 (Contado) o 2 (Crédito).',
            'payment_method_code.required' => 'El método de pago es obligatorio.',
            'payment_method_code.in'       => 'El método de pago no es válido.',
            'payment_due_date.required_if' => 'La fecha de vencimiento es obligatoria para pagos a crédito.',
            'payment_due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a hoy.',
            'items.required'               => 'La factura debe tener al menos un ítem.',
            'items.min'                    => 'La factura debe tener al menos un ítem.',
            'items.*.codigo.required'      => 'El código del ítem :position es obligatorio.',
            'items.*.nombre.required'      => 'El nombre del ítem :position es obligatorio.',
            'items.*.cantidad.required'    => 'La cantidad del ítem :position es obligatoria.',
            'items.*.cantidad.min'         => 'La cantidad del ítem :position debe ser mayor a 0.',
            'items.*.precio_unitario.required' => 'El precio del ítem :position es obligatorio.',
            'items.*.porcentaje_iva.required'  => 'El IVA del ítem :position es obligatorio.',
            'items.*.porcentaje_iva.in'        => 'El IVA del ítem :position debe ser 0, 5 o 19.',
        ];
    }
}
