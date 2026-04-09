<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: tabla de tokens OAuth2 de Factus.
 *
 * Persiste el token de acceso obtenido de la API de Factus mediante
 * el flujo OAuth2 Password Grant. El servicio de integración consulta
 * este registro para reutilizar un token vigente o solicitar uno nuevo
 * cuando ha expirado, evitando autenticaciones innecesarias.
 *
 * Solo existirá un registro activo a la vez — la aplicación siempre
 * usa el token más reciente (ordenado por created_at DESC).
 */
return new class extends Migration
{
    /**
     * Crea la tabla factus_tokens.
     */
    public function up(): void
    {
        Schema::create('factus_tokens', function (Blueprint $table) {
            $table->id();

            $table->text('access_token')
                ->comment('Bearer token JWT retornado por el endpoint /oauth/token de Factus');

            $table->string('token_type', 20)
                ->default('Bearer')
                ->comment('Tipo de token — siempre "Bearer" en Factus');

            $table->unsignedInteger('expires_in')
                ->comment('Vida útil del token en segundos desde su emisión');

            $table->timestamp('expires_at')
                ->comment('Timestamp exacto de expiración: created_at + expires_in segundos');

            $table->text('refresh_token')
                ->nullable()
                ->comment('Token de renovación — si Factus lo retorna en la respuesta');

            $table->timestamps();

            // Índice para ordenar y verificar vigencia rápidamente
            $table->index('expires_at', 'factus_tokens_expires_idx');
        });
    }

    /**
     * Elimina la tabla factus_tokens.
     */
    public function down(): void
    {
        Schema::dropIfExists('factus_tokens');
    }
};
