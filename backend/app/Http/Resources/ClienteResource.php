<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource del modelo Cliente.
 * Incluye los accessors calculados (nombre_completo, documento_formateado)
 * para que el frontend no tenga que reconstruirlos.
 */
class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'tipo_persona'                => $this->tipo_persona,
            'tipo_persona_label'          => $this->tipo_persona === 'J' ? 'Jurídica' : 'Natural',
            'tipo_documento_identidad_id' => $this->tipo_documento_identidad_id,
            'numero_documento'            => $this->numero_documento,
            'digito_verificacion'         => $this->digito_verificacion,
            'documento_formateado'        => $this->documento_formateado,
            'razon_social'                => $this->razon_social,
            'primer_nombre'               => $this->primer_nombre,
            'segundo_nombre'              => $this->segundo_nombre,
            'primer_apellido'             => $this->primer_apellido,
            'segundo_apellido'            => $this->segundo_apellido,
            'nombre_completo'             => $this->nombre_completo,
            'email'                       => $this->email,
            'telefono'                    => $this->telefono,
            'direccion'                   => $this->direccion,
            'municipio_id'                => $this->municipio_id,
            'municipio_nombre'            => $this->municipio_nombre,
            'departamento'                => $this->departamento,
            'obligations'                 => $this->obligations,
            'tribute_id'                  => $this->tribute_id,
            'activo'                      => $this->activo,
            'created_at'                  => $this->created_at,
            'updated_at'                  => $this->updated_at,
        ];
    }
}
