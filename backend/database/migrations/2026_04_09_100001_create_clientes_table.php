<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de clientes.
 *
 * Almacena los datos de clientes para facturación electrónica colombiana.
 * Incluye toda la información tributaria requerida por la DIAN:
 * tipo de persona, documento, obligaciones fiscales y régimen tributario.
 */
return new class extends Migration
{
    /**
     * Crea la tabla clientes con sus índices y restricciones.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // ── Tipo de persona y documento ──────────────────────────────────
            $table->string('tipo_persona', 1)
                ->comment('J: Jurídica, N: Natural');

            $table->unsignedSmallInteger('tipo_documento_identidad_id')
                ->comment('Código DIAN: 13=CC, 22=CE, 31=NIT, 41=Pasaporte, 42=DIE, 50=NIT extranjero');

            $table->string('numero_documento', 20);

            $table->string('digito_verificacion', 1)
                ->nullable()
                ->comment('Dígito de verificación — solo para NIT');

            // ── Identificación (persona jurídica o natural) ──────────────────
            $table->string('razon_social')
                ->nullable()
                ->comment('Nombre de la empresa — personas jurídicas');

            $table->string('primer_nombre', 50)
                ->nullable()
                ->comment('Primer nombre — personas naturales');

            $table->string('segundo_nombre', 50)->nullable();
            $table->string('primer_apellido', 50)->nullable();
            $table->string('segundo_apellido', 50)->nullable();

            // ── Contacto ─────────────────────────────────────────────────────
            $table->string('email');
            $table->string('telefono', 20)->nullable();

            // ── Dirección ────────────────────────────────────────────────────
            $table->string('direccion');

            $table->string('municipio_id', 10)
                ->comment('Código municipio DIAN — ej: 11001 = Bogotá D.C.');

            $table->string('municipio_nombre')
                ->nullable()
                ->comment('Nombre del municipio — almacenado para evitar lookups');

            $table->string('departamento')->nullable();

            // ── Información tributaria DIAN ───────────────────────────────────
            $table->json('obligations')
                ->nullable()
                ->comment('Array de códigos de obligaciones fiscales DIAN — ej: ["O-13","R-99-PN"]');

            $table->unsignedSmallInteger('tribute_id')
                ->default(21)
                ->comment('Código régimen tributario DIAN: 21=No responsable IVA, 22=Régimen ordinario, 48=Responsable IVA');

            // ── Control ───────────────────────────────────────────────────────
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // ── Índices ───────────────────────────────────────────────────────
            $table->unique(
                ['user_id', 'numero_documento', 'tipo_documento_identidad_id'],
                'clientes_documento_unique'
            );
            $table->index(['user_id', 'activo'], 'clientes_user_activo_idx');
            $table->index(['user_id', 'tipo_persona'], 'clientes_user_tipo_idx');
        });
    }

    /**
     * Elimina la tabla clientes.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
