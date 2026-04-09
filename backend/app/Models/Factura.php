<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo Factura — factura electrónica colombiana.
 *
 * Tabla central del sistema. Gestiona el ciclo de vida completo de una
 * factura electrónica:
 *
 *   borrador → emitida → (anulada)
 *
 * - borrador: creada localmente, aún no enviada a la API de Factus.
 * - emitida:  aceptada por Factus y aprobada por la DIAN. Tiene CUFE.
 * - anulada:  cancelada ante la DIAN a través de Factus.
 *
 * Solo las facturas en estado 'borrador' son editables.
 * Solo las facturas 'emitidas' pueden ser anuladas.
 *
 * Constantes disponibles:
 *   - ESTADO_*: estados del ciclo de vida
 *   - PAYMENT_FORM_*: formas de pago DIAN
 *   - PAYMENT_METHOD_*: métodos de pago DIAN más comunes
 *
 * Relaciones:
 *   - user: usuario que emitió la factura
 *   - cliente: receptor de la factura
 *   - items: líneas de detalle de la factura
 */

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factura extends Model
{
    use HasFactory, SoftDeletes;

    // ── Constantes de estado ──────────────────────────────────────────────────

    /** Factura creada pero no enviada a Factus — editable */
    public const ESTADO_BORRADOR = 'borrador';

    /** Factura aprobada por la DIAN vía Factus — tiene CUFE */
    public const ESTADO_EMITIDA = 'emitida';

    /** Factura anulada ante la DIAN — estado final */
    public const ESTADO_ANULADA = 'anulada';

    /** Todos los estados posibles */
    public const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_EMITIDA,
        self::ESTADO_ANULADA,
    ];

    // ── Constantes de pago DIAN ───────────────────────────────────────────────

    /** Pago de contado — al momento de la entrega */
    public const PAYMENT_FORM_CONTADO = 1;

    /** Pago a crédito — con fecha de vencimiento */
    public const PAYMENT_FORM_CREDITO = 2;

    /** Método de pago: efectivo */
    public const PAYMENT_METHOD_EFECTIVO = 10;

    /** Método de pago: cheque */
    public const PAYMENT_METHOD_CHEQUE = 20;

    /** Método de pago: transferencia débito bancaria */
    public const PAYMENT_METHOD_TRANSFERENCIA = 42;

    /** Método de pago: tarjeta de crédito */
    public const PAYMENT_METHOD_TARJETA_CREDITO = 48;

    /** Método de pago: tarjeta débito */
    public const PAYMENT_METHOD_TARJETA_DEBITO = 49;

    // ── Configuración del modelo ──────────────────────────────────────────────

    protected $table = 'facturas';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'cliente_id',
        'numbering_range_id',
        'prefijo',
        'numero',
        'numero_completo',
        'payment_form',
        'payment_method_code',
        'payment_due_date',
        'observaciones',
        'subtotal',
        'total_descuento',
        'total_iva',
        'total',
        'estado',
        'factus_id',
        'factus_numero',
        'factus_cufe',
        'factus_qr',
        'factus_pdf_base64',
        'factus_respuesta',
        'emitida_en',
        'anulada_en',
        'motivo_anulacion',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Retorna el usuario que emitió esta factura.
     *
     * @return BelongsTo<User, Factura>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retorna el cliente receptor de la factura.
     *
     * @return BelongsTo<Cliente, Factura>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Retorna todos los ítems (líneas de detalle) de la factura.
     *
     * @return HasMany<FacturaItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FacturaItem::class);
    }

    // ── Métodos de dominio ────────────────────────────────────────────────────

    /**
     * Indica si la factura está en estado borrador.
     *
     * @return bool True si la factura es borrador
     */
    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    /**
     * Indica si la factura ha sido emitida exitosamente ante la DIAN.
     *
     * @return bool True si la factura está emitida
     */
    public function estaEmitida(): bool
    {
        return $this->estado === self::ESTADO_EMITIDA;
    }

    /**
     * Indica si la factura fue anulada.
     *
     * @return bool True si la factura está anulada
     */
    public function estaAnulada(): bool
    {
        return $this->estado === self::ESTADO_ANULADA;
    }

    /**
     * Indica si la factura puede ser editada.
     * Solo los borradores son editables.
     *
     * @return bool True si se pueden modificar los datos
     */
    public function esEditable(): bool
    {
        return $this->esBorrador();
    }

    /**
     * Indica si la factura puede ser enviada a Factus para emisión.
     * Solo los borradores con al menos un ítem pueden emitirse.
     *
     * @return bool True si puede ser enviada a Factus
     */
    public function puedeEmitirse(): bool
    {
        return $this->esBorrador() && $this->items()->exists();
    }

    /**
     * Indica si la factura puede ser anulada ante la DIAN.
     * Solo las facturas emitidas pueden anularse.
     *
     * @return bool True si puede ser anulada
     */
    public function puedeAnularse(): bool
    {
        return $this->estaEmitida();
    }

    /**
     * Indica si la factura tiene pago a crédito con fecha de vencimiento.
     *
     * @return bool True si es pago a crédito
     */
    public function esCredito(): bool
    {
        return $this->payment_form === self::PAYMENT_FORM_CREDITO;
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Retorna la etiqueta legible del estado actual.
     *
     * @return Attribute<string, never>
     */
    protected function estadoLabel(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->estado) {
                self::ESTADO_BORRADOR => 'Borrador',
                self::ESTADO_EMITIDA  => 'Emitida',
                self::ESTADO_ANULADA  => 'Anulada',
                default               => 'Desconocido',
            }
        );
    }

    /**
     * Retorna el color CSS asociado al estado para la UI.
     *
     * @return Attribute<string, never>
     */
    protected function estadoColor(): Attribute
    {
        return Attribute::make(
            get: fn (): string => match ($this->estado) {
                self::ESTADO_BORRADOR => 'yellow',
                self::ESTADO_EMITIDA  => 'green',
                self::ESTADO_ANULADA  => 'red',
                default               => 'gray',
            }
        );
    }

    // ── Query Scopes ──────────────────────────────────────────────────────────

    /**
     * Filtra solo las facturas en borrador.
     *
     * @param  Builder<Factura>  $query
     * @return Builder<Factura>
     */
    public function scopeBorradores(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_BORRADOR);
    }

    /**
     * Filtra solo las facturas emitidas ante la DIAN.
     *
     * @param  Builder<Factura>  $query
     * @return Builder<Factura>
     */
    public function scopeEmitidas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_EMITIDA);
    }

    /**
     * Filtra solo las facturas anuladas.
     *
     * @param  Builder<Factura>  $query
     * @return Builder<Factura>
     */
    public function scopeAnuladas(Builder $query): Builder
    {
        return $query->where('estado', self::ESTADO_ANULADA);
    }

    /**
     * Filtra facturas del mes y año indicados.
     *
     * @param  Builder<Factura>  $query
     * @param  int               $mes  Número de mes (1-12)
     * @param  int               $anio Año de cuatro dígitos
     * @return Builder<Factura>
     */
    public function scopeDelMes(Builder $query, int $mes, int $anio): Builder
    {
        return $query->whereMonth('created_at', $mes)
            ->whereYear('created_at', $anio);
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
            'subtotal'          => 'float',
            'total_descuento'   => 'float',
            'total_iva'         => 'float',
            'total'             => 'float',
            'payment_form'      => 'integer',
            'payment_method_code' => 'integer',
            'payment_due_date'  => 'date',
            'factus_respuesta'  => 'array',
            'emitida_en'        => 'datetime',
            'anulada_en'        => 'datetime',
        ];
    }
}
