<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo Cliente — datos del receptor de las facturas electrónicas.
 *
 * Almacena toda la información tributaria colombiana necesaria para emitir
 * facturas válidas ante la DIAN: tipo de persona, tipo de documento,
 * obligaciones fiscales y régimen tributario.
 *
 * Constantes disponibles:
 *   - TIPO_PERSONA_*: J (jurídica) o N (natural)
 *   - TIPO_DOCUMENTO_*: códigos DIAN para tipos de documento
 *   - TRIBUTE_*: códigos de régimen tributario DIAN
 *
 * Relaciones:
 *   - user: usuario propietario del cliente
 *   - facturas: facturas emitidas a este cliente
 */

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    // ── Constantes DIAN ───────────────────────────────────────────────────────

    /** Persona jurídica (empresa, sociedad) */
    public const TIPO_PERSONA_JURIDICA = 'J';

    /** Persona natural (individuo) */
    public const TIPO_PERSONA_NATURAL = 'N';

    /** Cédula de ciudadanía */
    public const TIPO_DOCUMENTO_CC = 13;

    /** Cédula de extranjería */
    public const TIPO_DOCUMENTO_CE = 22;

    /** Número de Identificación Tributaria */
    public const TIPO_DOCUMENTO_NIT = 31;

    /** Pasaporte */
    public const TIPO_DOCUMENTO_PASAPORTE = 41;

    /** Documento de identificación extranjero */
    public const TIPO_DOCUMENTO_DIE = 42;

    /** NIT de otros países */
    public const TIPO_DOCUMENTO_NIT_EXTRANJERO = 50;

    /** Régimen tributario: No responsable de IVA */
    public const TRIBUTE_NO_RESPONSABLE_IVA = 21;

    /** Régimen tributario: Régimen ordinario */
    public const TRIBUTE_REGIMEN_ORDINARIO = 22;

    /** Régimen tributario: Responsable de IVA */
    public const TRIBUTE_RESPONSABLE_IVA = 48;

    // ── Configuración del modelo ──────────────────────────────────────────────

    protected $table = 'clientes';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tipo_persona',
        'tipo_documento_identidad_id',
        'numero_documento',
        'digito_verificacion',
        'razon_social',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'email',
        'telefono',
        'direccion',
        'municipio_id',
        'municipio_nombre',
        'departamento',
        'obligations',
        'tribute_id',
        'activo',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Retorna el usuario propietario de este cliente.
     *
     * @return BelongsTo<User, Cliente>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retorna todas las facturas emitidas a este cliente.
     *
     * @return HasMany<Factura>
     */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Retorna el nombre completo para mostrar en la UI.
     *
     * Para persona jurídica devuelve la razón social.
     * Para persona natural concatena nombres y apellidos.
     *
     * @return Attribute<string, never>
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->tipo_persona === self::TIPO_PERSONA_JURIDICA) {
                    return (string) $this->razon_social;
                }

                return trim(implode(' ', array_filter([
                    $this->primer_nombre,
                    $this->segundo_nombre,
                    $this->primer_apellido,
                    $this->segundo_apellido,
                ])));
            }
        );
    }

    /**
     * Retorna el documento formateado con dígito de verificación si aplica.
     *
     * Para NIT: "900123456-7"
     * Para otros: el número tal cual.
     *
     * @return Attribute<string, never>
     */
    protected function documentoFormateado(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if ($this->tipo_documento_identidad_id === self::TIPO_DOCUMENTO_NIT
                    && $this->digito_verificacion !== null) {
                    return "{$this->numero_documento}-{$this->digito_verificacion}";
                }

                return (string) $this->numero_documento;
            }
        );
    }

    /**
     * Indica si el cliente es persona jurídica (empresa).
     *
     * @return Attribute<bool, never>
     */
    protected function esJuridica(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->tipo_persona === self::TIPO_PERSONA_JURIDICA
        );
    }

    // ── Query Scopes ──────────────────────────────────────────────────────────

    /**
     * Filtra solo los clientes activos.
     *
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Filtra solo las personas jurídicas (empresas).
     *
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeJuridicas(Builder $query): Builder
    {
        return $query->where('tipo_persona', self::TIPO_PERSONA_JURIDICA);
    }

    /**
     * Filtra solo las personas naturales.
     *
     * @param  Builder<Cliente>  $query
     * @return Builder<Cliente>
     */
    public function scopeNaturales(Builder $query): Builder
    {
        return $query->where('tipo_persona', self::TIPO_PERSONA_NATURAL);
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    /**
     * Conversión de atributos a tipos nativos de PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'obligations'               => 'array',
            'activo'                    => 'boolean',
            'tipo_documento_identidad_id' => 'integer',
            'tribute_id'                => 'integer',
        ];
    }
}
