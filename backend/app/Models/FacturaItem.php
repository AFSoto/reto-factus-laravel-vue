<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo FacturaItem — línea de detalle de una factura electrónica.
 *
 * Cada registro representa un ítem dentro de una factura: producto,
 * cantidad, precio, descuento e impuesto. Los datos del producto
 * se almacenan como snapshot al momento de crear la factura para
 * garantizar integridad histórica del precio y la clasificación DIAN.
 *
 * Los valores calculados (valor_descuento, valor_iva, subtotal, total)
 * se computan en el FacturaService y se persisten para evitar
 * recalcularlos en cada lectura.
 *
 * Relaciones:
 *   - factura: factura a la que pertenece este ítem
 *   - producto: producto del catálogo (nullable para conceptos libres)
 */

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaItem extends Model
{
    use HasFactory;

    // ── Configuración del modelo ──────────────────────────────────────────────

    protected $table = 'factura_items';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'factura_id',
        'producto_id',
        'codigo',
        'nombre',
        'descripcion',
        'unidad_medida_id',
        'tipo_item_identificacion_id',
        'codigo_referencia',
        'cantidad',
        'precio_unitario',
        'porcentaje_descuento',
        'valor_descuento',
        'porcentaje_iva',
        'valor_iva',
        'tribute_id',
        'subtotal',
        'total',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Retorna la factura a la que pertenece este ítem.
     *
     * @return BelongsTo<Factura, FacturaItem>
     */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    /**
     * Retorna el producto del catálogo asociado a este ítem.
     * Puede ser null si el ítem fue ingresado como concepto libre.
     *
     * @return BelongsTo<Producto, FacturaItem>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    // ── Métodos de cálculo ────────────────────────────────────────────────────

    /**
     * Recalcula y asigna todos los valores derivados del ítem.
     *
     * Calcula: valor_descuento, base gravable (subtotal) y valor_iva.
     * El total = subtotal + valor_iva.
     *
     * Debe llamarse antes de guardar cuando cambie cantidad, precio,
     * porcentaje de descuento o porcentaje de IVA.
     *
     * @return void
     */
    public function recalcularTotales(): void
    {
        $precioBase = (float) $this->cantidad * (float) $this->precio_unitario;

        $this->valor_descuento = round(
            $precioBase * ((float) $this->porcentaje_descuento / 100),
            2
        );

        $this->subtotal = round($precioBase - $this->valor_descuento, 2);

        $this->valor_iva = round(
            $this->subtotal * ((float) $this->porcentaje_iva / 100),
            2
        );

        $this->total = round($this->subtotal + $this->valor_iva, 2);
    }

    /**
     * Indica si este ítem tiene descuento aplicado.
     *
     * @return bool True si el porcentaje de descuento es mayor a cero
     */
    public function tieneDescuento(): bool
    {
        return $this->porcentaje_descuento > 0;
    }

    /**
     * Indica si este ítem tiene IVA aplicado.
     *
     * @return bool True si la tarifa de IVA es mayor a cero
     */
    public function tieneIva(): bool
    {
        return $this->porcentaje_iva > 0;
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
            'cantidad'               => 'float',
            'precio_unitario'        => 'float',
            'porcentaje_descuento'   => 'float',
            'valor_descuento'        => 'float',
            'porcentaje_iva'         => 'float',
            'valor_iva'              => 'float',
            'tribute_id'             => 'integer',
            'subtotal'               => 'float',
            'total'                  => 'float',
        ];
    }
}
