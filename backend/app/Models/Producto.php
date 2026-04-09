<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo Producto — catálogo de productos y servicios facturables.
 *
 * Almacena el catálogo de ítems que el usuario puede incluir en sus facturas.
 * Cada producto tiene clasificación DIAN (unidad de medida, tipo de identificación)
 * y configuración de impuestos (tarifa IVA, código de tributo).
 *
 * Al agregar un producto a una factura, sus valores se copian como snapshot
 * en factura_items para preservar la integridad histórica.
 *
 * Constantes disponibles:
 *   - IVA_*: tarifas de IVA permitidas por la DIAN
 *   - TRIBUTE_*: códigos de tipo de impuesto DIAN
 *   - UNIDAD_MEDIDA_*: códigos de unidad de medida más comunes
 *
 * Relaciones:
 *   - user: usuario propietario del catálogo
 *   - facturaItems: ítems de factura que referencian este producto
 */

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    // ── Constantes DIAN ───────────────────────────────────────────────────────

    /** IVA excluido o exento — 0% */
    public const IVA_CERO = 0.00;

    /** IVA reducido para bienes de primera necesidad — 5% */
    public const IVA_CINCO = 5.00;

    /** IVA general — 19% */
    public const IVA_GENERAL = 19.00;

    /** Impuesto al Valor Agregado */
    public const TRIBUTE_IVA = 1;

    /** Impuesto Nacional al Consumo */
    public const TRIBUTE_INC = 4;

    /** INC Bolsas Plásticas */
    public const TRIBUTE_INC_BOLSAS = 6;

    /** Unidad de medida estándar — Unidad */
    public const UNIDAD_MEDIDA_UNIDAD = '94';

    /** Tipo identificación ítem estándar */
    public const TIPO_ITEM_ESTANDAR = '999';

    // ── Configuración del modelo ──────────────────────────────────────────────

    protected $table = 'productos';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'codigo',
        'nombre',
        'descripcion',
        'unidad_medida_id',
        'tipo_item_identificacion_id',
        'codigo_referencia',
        'precio_unitario',
        'porcentaje_descuento',
        'porcentaje_iva',
        'tribute_id',
        'activo',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Retorna el usuario propietario del catálogo.
     *
     * @return BelongsTo<User, Producto>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Retorna todos los ítems de factura que usan este producto.
     *
     * @return HasMany<FacturaItem>
     */
    public function facturaItems(): HasMany
    {
        return $this->hasMany(FacturaItem::class);
    }

    // ── Métodos de negocio ────────────────────────────────────────────────────

    /**
     * Calcula el precio final con IVA incluido.
     *
     * @return float Precio unitario más el IVA configurado
     */
    public function precioConIva(): float
    {
        return (float) $this->precio_unitario * (1 + ($this->porcentaje_iva / 100));
    }

    /**
     * Indica si el producto tiene IVA diferente de cero.
     *
     * @return bool True si aplica alguna tarifa de IVA
     */
    public function tieneIva(): bool
    {
        return $this->porcentaje_iva > 0;
    }

    // ── Query Scopes ──────────────────────────────────────────────────────────

    /**
     * Filtra solo los productos activos.
     *
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Filtra productos con IVA al 19%.
     *
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
     */
    public function scopeConIvaGeneral(Builder $query): Builder
    {
        return $query->where('porcentaje_iva', self::IVA_GENERAL);
    }

    /**
     * Filtra productos excluidos o exentos de IVA.
     *
     * @param  Builder<Producto>  $query
     * @return Builder<Producto>
     */
    public function scopeSinIva(Builder $query): Builder
    {
        return $query->where('porcentaje_iva', self::IVA_CERO);
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
            'precio_unitario'       => 'float',
            'porcentaje_descuento'  => 'float',
            'porcentaje_iva'        => 'float',
            'tribute_id'            => 'integer',
            'activo'                => 'boolean',
        ];
    }
}
