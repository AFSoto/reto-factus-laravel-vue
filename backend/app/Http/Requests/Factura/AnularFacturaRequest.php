<?php

declare(strict_types=1);

namespace App\Http\Requests\Factura;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validación para anular una factura emitida.
 * La DIAN requiere un motivo de anulación descriptivo.
 */
class AnularFacturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de anulación es obligatorio.',
            'motivo.min'      => 'El motivo de anulación debe tener al menos 10 caracteres.',
            'motivo.max'      => 'El motivo de anulación no puede superar los 500 caracteres.',
        ];
    }
}
