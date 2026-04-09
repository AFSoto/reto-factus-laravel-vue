<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de facturas.
 *
 * Tabla central del sistema. Almacena las facturas electrónicas en todos
 * sus estados: borrador (antes de enviar a Factus), emitida (aprobada por
 * la DIAN vía Factus) y anulada. Guarda tanto los datos propios como
 * la respuesta completa de la API de Factus (CUFE, QR, PDF).
 */
return new class extends Migration
{
    /**
     * Crea la tabla facturas con sus índices y restricciones.
     */
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete()
                ->comment('No se puede eliminar un cliente con facturas');

            // ── Numeración ────────────────────────────────────────────────────
            $table->unsignedBigInteger('numbering_range_id')
                ->comment('ID del rango de numeración configurado en Factus');

            $table->string('prefijo', 10)
                ->nullable()
                ->comment('Prefijo del rango — ej: SETP');

            $table->unsignedBigInteger('numero')
                ->comment('Número consecutivo dentro del rango');

            $table->string('numero_completo', 25)
                ->nullable()
                ->comment('Prefijo + número — ej: SETP990000001');

            // ── Pago ──────────────────────────────────────────────────────────
            $table->unsignedTinyInteger('payment_form')
                ->default(1)
                ->comment('Forma de pago DIAN: 1=Contado, 2=Crédito');

            $table->unsignedSmallInteger('payment_method_code')
                ->default(10)
                ->comment('Código método pago DIAN: 10=Efectivo, 42=Transferencia, 48=Tarjeta crédito');

            $table->date('payment_due_date')
                ->nullable()
                ->comment('Fecha vencimiento — solo para pago a crédito (payment_form=2)');

            $table->text('observaciones')->nullable();

            // ── Totales ───────────────────────────────────────────────────────
            $table->decimal('subtotal', 15, 2)
                ->default(0)
                ->comment('Suma de (cantidad × precio) sin descuentos ni impuestos');

            $table->decimal('total_descuento', 15, 2)
                ->default(0)
                ->comment('Suma total de todos los descuentos aplicados');

            $table->decimal('total_iva', 15, 2)
                ->default(0)
                ->comment('Suma total del IVA de todos los ítems');

            $table->decimal('total', 15, 2)
                ->default(0)
                ->comment('Valor total a pagar: subtotal - descuentos + IVA');

            // ── Estado ────────────────────────────────────────────────────────
            $table->string('estado', 20)
                ->default('borrador')
                ->comment('Estados posibles: borrador | emitida | anulada');

            // ── Respuesta de la API Factus ────────────────────────────────────
            $table->unsignedBigInteger('factus_id')
                ->nullable()
                ->comment('ID numérico de la factura retornado por Factus');

            $table->string('factus_numero', 30)
                ->nullable()
                ->comment('Número de factura asignado y confirmado por Factus');

            $table->string('factus_cufe', 200)
                ->nullable()
                ->comment('Código Único de Factura Electrónica emitido por la DIAN');

            $table->text('factus_qr')
                ->nullable()
                ->comment('Contenido textual del código QR de la factura');

            $table->text('factus_pdf_base64')
                ->nullable()
                ->comment('PDF de la factura en Base64 retornado por Factus');

            $table->json('factus_respuesta')
                ->nullable()
                ->comment('Payload completo de la respuesta de Factus para auditoría');

            // ── Auditoría de estado ───────────────────────────────────────────
            $table->timestamp('emitida_en')
                ->nullable()
                ->comment('Fecha y hora en que la factura fue emitida exitosamente');

            $table->timestamp('anulada_en')
                ->nullable()
                ->comment('Fecha y hora en que la factura fue anulada');

            $table->text('motivo_anulacion')
                ->nullable()
                ->comment('Motivo ingresado por el usuario al anular la factura');

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ───────────────────────────────────────────────────────
            $table->index(['user_id', 'estado'], 'facturas_user_estado_idx');
            $table->index(['user_id', 'created_at'], 'facturas_user_fecha_idx');
            $table->index(['user_id', 'cliente_id'], 'facturas_user_cliente_idx');
            $table->index('factus_id', 'facturas_factus_id_idx');
            $table->index('factus_cufe', 'facturas_cufe_idx');
        });
    }

    /**
     * Elimina la tabla facturas.
     */
    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
