<?php

declare(strict_types=1);

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación para actualizar un cliente (PUT — reemplazo completo).
 * Mismas reglas que StoreClienteRequest — el Service verifica pertenencia.
 */
class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_persona'                => ['required', 'string', Rule::in(['J', 'N'])],
            'tipo_documento_identidad_id' => ['required', 'integer', Rule::in([13, 22, 31, 41, 42, 50])],
            'numero_documento'            => ['required', 'string', 'max:20'],
            'digito_verificacion'         => ['nullable', 'string', 'max:2'],
            'razon_social'                => [
                Rule::requiredIf($this->input('tipo_persona') === 'J'),
                'nullable', 'string', 'max:300',
            ],
            'primer_nombre'               => [
                Rule::requiredIf($this->input('tipo_persona') === 'N'),
                'nullable', 'string', 'max:100',
            ],
            'segundo_nombre'              => ['nullable', 'string', 'max:100'],
            'primer_apellido'             => [
                Rule::requiredIf($this->input('tipo_persona') === 'N'),
                'nullable', 'string', 'max:100',
            ],
            'segundo_apellido'            => ['nullable', 'string', 'max:100'],
            'email'                       => ['required', 'string', 'email', 'max:150'],
            'telefono'                    => ['nullable', 'string', 'max:20'],
            'direccion'                   => ['required', 'string', 'max:255'],
            'municipio_id'                => ['required', 'string', 'max:10'],
            'municipio_nombre'            => ['nullable', 'string', 'max:100'],
            'departamento'                => ['nullable', 'string', 'max:100'],
            'obligations'                 => ['nullable', 'array'],
            'obligations.*'               => ['string'],
            'tribute_id'                  => ['required', 'integer', Rule::in([21, 22, 48])],
            'activo'                      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_persona.required'                => 'El tipo de persona es obligatorio.',
            'tipo_persona.in'                      => 'El tipo de persona debe ser J (Jurídica) o N (Natural).',
            'tipo_documento_identidad_id.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento_identidad_id.in'       => 'El tipo de documento no es válido según la DIAN.',
            'numero_documento.required'            => 'El número de documento es obligatorio.',
            'razon_social.required'                => 'La razón social es obligatoria para personas jurídicas.',
            'primer_nombre.required'               => 'El primer nombre es obligatorio para personas naturales.',
            'primer_apellido.required'             => 'El primer apellido es obligatorio para personas naturales.',
            'email.required'                       => 'El correo electrónico es obligatorio.',
            'email.email'                          => 'El correo electrónico no tiene un formato válido.',
            'direccion.required'                   => 'La dirección es obligatoria.',
            'municipio_id.required'                => 'El municipio es obligatorio.',
            'tribute_id.required'                  => 'El régimen tributario es obligatorio.',
            'tribute_id.in'                        => 'El régimen tributario no es válido.',
        ];
    }
}
