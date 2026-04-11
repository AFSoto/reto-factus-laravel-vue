<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de ítems de factura.
 *
 * Cada registro representa una línea dentro de una factura.
 * Los datos del producto (nombre, precio, IVA) se copian al momento
 * de crear la factura para garantizar integridad histórica: si el
 * producto cambia de precio después, la factura conserva el valor original.
 *
 * La relación con productos es nullable — permite facturar conceptos
 * libres sin un producto registrado en el catálogo.
 */
return new class extends Migration
{
    /**
     * Crea la tabla factura_items con sus índices y restricciones.
     */
    public function up(): void
    {
        Schema::create('factura_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('factura_id')
                ->constrained('facturas')
                ->cascadeOnDelete()
                ->comment('Al eliminar la factura se eliminan sus ítems');

            $table->foreignId('producto_id')
                ->nullable()
                ->constrained('productos')
                ->nullOnDelete()
                ->comment('Producto del catálogo — nullable para conceptos libres');

            // ── Snapshot del producto al momento de facturar ──────────────────
            $table->string('codigo', 50)
                ->nullable()
                ->comment('Copia del código del producto al momento de emitir');

            $table->string('nombre')
                ->comment('Copia del nombre del producto — inmutable en historial');

            $table->text('descripcion')
                ->nullable()
                ->comment('Descripción adicional del ítem en esta factura específica');

            // ── Clasificación DIAN (snapshot) ─────────────────────────────────
            $table->string('unidad_medida_id', 10)
                ->default('94')
                ->comment('Código unidad medida DIAN copiado del producto');

            $table->string('tipo_item_identificacion_id', 20)
                ->default('999')
                ->comment('Tipo identificación ítem DIAN copiado del producto');

            $table->string('codigo_referencia', 50)
                ->nullable()
                ->comment('Código de referencia EAN/GTIN copiado del producto');

            // ── Cantidades y precio base ──────────────────────────────────────
            $table->decimal('cantidad', 10, 3)
                ->default(1)
                ->comment('Permite fracciones — ej: 0.5 kg, 1.25 metros');

            $table->decimal('precio_unitario', 15, 2)
                ->default(0)
                ->comment('Precio unitario sin descuento ni impuesto');

            // ── Descuento ─────────────────────────────────────────────────────
            $table->decimal('porcentaje_descuento', 5, 2)
                ->default(0)
                ->comment('Porcentaje de descuento sobre el precio unitario');

            $table->decimal('valor_descuento', 15, 2)
                ->default(0)
                ->comment('Valor monetario del descuento: cantidad × precio × (pct/100)');

            // ── Impuesto ──────────────────────────────────────────────────────
            $table->decimal('porcentaje_iva', 5, 2)
                ->default(19.00)
                ->comment('Tarifa de IVA aplicada: 0, 5 o 19 por ciento');

            $table->decimal('valor_iva', 15, 2)
                ->default(0)
                ->comment('Valor monetario del IVA calculado sobre la base gravable');

            $table->unsignedSmallInteger('tribute_id')
                ->default(1)
                ->comment('Código impuesto DIAN: 1=IVA, 4=INC');

            // ── Totales calculados ────────────────────────────────────────────
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->comment('Base gravable: (cantidad × precio) - descuento');

            $table->decimal('total', 15, 2)
                ->default(0)
                ->comment('Total del ítem: subtotal + valor_iva');

            $table->timestamps();

            // ── Índices ───────────────────────────────────────────────────────
            $table->index('factura_id', 'factura_items_factura_idx');
            $table->index('producto_id', 'factura_items_producto_idx');
        });
    }

    /**
     * Elimina la tabla factura_items.
     */
    public function down(): void
    {
        Schema::dropIfExists('factura_items');
    }
};
