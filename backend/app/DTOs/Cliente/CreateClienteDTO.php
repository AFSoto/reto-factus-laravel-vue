<?php

declare(strict_types=1);

namespace App\DTOs\Cliente;

/**
 * DTO para crear o actualizar un cliente.
 *
 * Transporta los datos validados desde el Service hacia el Repository.
 * Al ser una clase readonly, sus propiedades son inmutables después de la
 * construcción — garantiza que los datos no se corrompen en tránsito.
 *
 * Flujo de uso:
 *   Request → FormRequest::validated() → CreateClienteDTO::fromArray() → ClienteRepository::crear()
 *
 * Nunca instanciar directamente en un Controller — eso es responsabilidad del Service.
 */
readonly class CreateClienteDTO
{
    /**
     * Construye el DTO con todos los campos del cliente.
     *
     * @param  int          $userId                   ID del usuario autenticado propietario del cliente
     * @param  string       $tipoPersona              'J' = Jurídica | 'N' = Natural
     * @param  int          $tipoDocumentoIdentidadId Código DIAN del tipo de documento
     * @param  string       $numeroDocumento          Número del documento de identidad
     * @param  string|null  $digitoVerificacion       Dígito de verificación — solo para NIT
     * @param  string|null  $razonSocial              Nombre de la empresa — solo para jurídicas
     * @param  string|null  $primerNombre             Primer nombre — solo para naturales
     * @param  string|null  $segundoNombre            Segundo nombre opcional
     * @param  string|null  $primerApellido           Primer apellido — solo para naturales
     * @param  string|null  $segundoApellido          Segundo apellido opcional
     * @param  string       $email                    Correo electrónico del cliente
     * @param  string|null  $telefono                 Teléfono de contacto
     * @param  string       $direccion                Dirección física del cliente
     * @param  string       $municipioId              Código DIAN del municipio (ej: '11001')
     * @param  string|null  $municipioNombre          Nombre del municipio para mostrar en UI
     * @param  string|null  $departamento             Nombre del departamento
     * @param  array|null   $obligations              Códigos de obligaciones fiscales DIAN
     * @param  int          $tributeId                Código régimen tributario DIAN
     * @param  bool         $activo                   Estado activo del cliente
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $tipoPersona,
        public readonly int $tipoDocumentoIdentidadId,
        public readonly string $numeroDocumento,
        public readonly ?string $digitoVerificacion,
        public readonly ?string $razonSocial,
        public readonly ?string $primerNombre,
        public readonly ?string $segundoNombre,
        public readonly ?string $primerApellido,
        public readonly ?string $segundoApellido,
        public readonly string $email,
        public readonly ?string $telefono,
        public readonly string $direccion,
        public readonly string $municipioId,
        public readonly ?string $municipioNombre,
        public readonly ?string $departamento,
        public readonly ?array $obligations,
        public readonly int $tributeId,
        public readonly bool $activo = true,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Crea el DTO a partir de un array de datos ya validados.
     *
     * Se espera que el array venga de FormRequest::validated() en el Controller,
     * procesado por el Service antes de instanciar el DTO.
     *
     * @param  array  $data    Array con los datos del cliente (misma forma que el constructor)
     * @param  int    $userId  ID del usuario autenticado — se obtiene del Service, no del array
     * @return static
     */
    public static function fromArray(array $data, int $userId): static
    {
        return new static(
            userId:                   $userId,
            tipoPersona:              $data['tipo_persona'],
            tipoDocumentoIdentidadId: (int) $data['tipo_documento_identidad_id'],
            numeroDocumento:          $data['numero_documento'],
            digitoVerificacion:       $data['digito_verificacion'] ?? null,
            razonSocial:              $data['razon_social'] ?? null,
            primerNombre:             $data['primer_nombre'] ?? null,
            segundoNombre:            $data['segundo_nombre'] ?? null,
            primerApellido:           $data['primer_apellido'] ?? null,
            segundoApellido:          $data['segundo_apellido'] ?? null,
            email:                    $data['email'],
            telefono:                 $data['telefono'] ?? null,
            direccion:                $data['direccion'],
            municipioId:              $data['municipio_id'],
            municipioNombre:          $data['municipio_nombre'] ?? null,
            departamento:             $data['departamento'] ?? null,
            obligations:              $data['obligations'] ?? null,
            tributeId:                (int) $data['tribute_id'],
            activo:                   (bool) ($data['activo'] ?? true),
        );
    }

    // ── Conversión ────────────────────────────────────────────────────────────

    /**
     * Convierte el DTO a array para persistir mediante el repositorio.
     *
     * Los nombres de clave son snake_case para coincidir con los campos
     * de la tabla y los atributos fillable del modelo Cliente.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'                     => $this->userId,
            'tipo_persona'                => $this->tipoPersona,
            'tipo_documento_identidad_id' => $this->tipoDocumentoIdentidadId,
            'numero_documento'            => $this->numeroDocumento,
            'digito_verificacion'         => $this->digitoVerificacion,
            'razon_social'                => $this->razonSocial,
            'primer_nombre'               => $this->primerNombre,
            'segundo_nombre'              => $this->segundoNombre,
            'primer_apellido'             => $this->primerApellido,
            'segundo_apellido'            => $this->segundoApellido,
            'email'                       => $this->email,
            'telefono'                    => $this->telefono,
            'direccion'                   => $this->direccion,
            'municipio_id'                => $this->municipioId,
            'municipio_nombre'            => $this->municipioNombre,
            'departamento'                => $this->departamento,
            'obligations'                 => $this->obligations,
            'tribute_id'                  => $this->tributeId,
            'activo'                      => $this->activo,
        ];
    }

    // ── Métodos de consulta ───────────────────────────────────────────────────

    /**
     * Indica si el cliente es persona jurídica (empresa).
     *
     * @return bool
     */
    public function esPersonaJuridica(): bool
    {
        return $this->tipoPersona === 'J';
    }

    /**
     * Indica si el documento es de tipo NIT (código DIAN 31).
     *
     * @return bool
     */
    public function esNit(): bool
    {
        return $this->tipoDocumentoIdentidadId === 31;
    }
}
