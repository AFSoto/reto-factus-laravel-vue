<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de productos.
 *
 * Almacena productos y servicios disponibles para facturar.
 * Incluye clasificación DIAN (unidad de medida, tipo de ítem)
 * y configuración de impuestos (IVA) requerida por Factus.
 */
return new class extends Migration
{
    /**
     * Crea la tabla productos con sus índices y restricciones.
     */
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ── Identificación del producto ───────────────────────────────────
            $table->string('codigo', 50)
                ->comment('Código interno del producto o servicio');

            $table->string('nombre');
            $table->text('descripcion')->nullable();

            // ── Clasificación DIAN ────────────────────────────────────────────
            $table->string('unidad_medida_id', 10)
                ->default('94')
                ->comment('Código unidad medida DIAN — 94=Unidad, 70=Cm³, NAR=Unidad NAR');

            $table->string('tipo_item_identificacion_id', 20)
                ->default('999')
                ->comment('Tipo identificación ítem DIAN — 999=Estándar, EAN=Código de barras, PART=Número de pieza');

            $table->string('codigo_referencia', 50)
                ->nullable()
                ->comment('Código de referencia externo — EAN, GTIN, etc.');

            // ── Precio e impuestos ────────────────────────────────────────────
            $table->decimal('precio_unitario', 15, 2)
                ->default(0)
                ->comment('Precio base sin impuestos');

            $table->decimal('porcentaje_descuento', 5, 2)
                ->default(0)
                ->comment('Porcentaje de descuento aplicado por defecto');

            $table->decimal('porcentaje_iva', 5, 2)
                ->default(19.00)
                ->comment('Tarifa de IVA: 0, 5 o 19 por ciento');

            $table->unsignedSmallInteger('tribute_id')
                ->default(1)
                ->comment('Código impuesto DIAN: 1=IVA, 4=INC, 6=INC Bolsas');

            // ── Control ───────────────────────────────────────────────────────
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // ── Índices ───────────────────────────────────────────────────────
            $table->unique(['user_id', 'codigo'], 'productos_user_codigo_unique');
            $table->index(['user_id', 'activo'], 'productos_user_activo_idx');
        });
    }

    /**
     * Elimina la tabla productos.
     */
    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
