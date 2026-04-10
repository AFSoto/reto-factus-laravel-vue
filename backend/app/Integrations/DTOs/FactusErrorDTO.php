<?php

declare(strict_types=1);

namespace App\Integrations\DTOs;

/**
 * DTO que encapsula una respuesta de error de la API de Factus.
 *
 * El FactusClient lo usa internamente para parsear respuestas con
 * status >= 400 antes de construir la FactusApiException correspondiente.
 * Permite acceder a los campos del error de forma tipada.
 */
readonly class FactusErrorDTO
{
    /**
     * @param  int     $status   Código HTTP de la respuesta
     * @param  string  $message  Mensaje de error principal de Factus
     * @param  array   $errors   Errores de validación por campo (puede estar vacío)
     * @param  array   $raw      Payload completo sin procesar para auditoría
     */
    public function __construct(
        public readonly int $status,
        public readonly string $message,
        public readonly array $errors,
        public readonly array $raw,
    ) {}

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Construye el DTO desde el payload JSON de una respuesta de error.
     *
     * Maneja los dos formatos de error que puede retornar Factus:
     *   1. { "message": "...", "errors": { "campo": ["error"] } }
     *   2. { "error": "...", "error_description": "..." }  (OAuth2 errors)
     *
     * @param  int    $status  Código HTTP de la respuesta
     * @param  array  $data    Payload JSON parseado de la respuesta
     * @return static
     */
    public static function fromResponse(int $status, array $data): static
    {
        // Formato OAuth2 (errores de autenticación)
        if (isset($data['error'])) {
            return new static(
                status:  $status,
                message: $data['error_description'] ?? $data['error'],
                errors:  [],
                raw:     $data,
            );
        }

        // Formato estándar de la API Factus
        return new static(
            status:  $status,
            message: $data['message'] ?? "Error HTTP {$status}",
            errors:  (array) ($data['errors'] ?? []),
            raw:     $data,
        );
    }

    // ── Métodos de consulta ───────────────────────────────────────────────────

    /**
     * Indica si la respuesta de error incluye errores de campo.
     *
     * @return bool
     */
    public function tieneErroresDeCampo(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Indica si fue un error de autenticación.
     *
     * @return bool
     */
    public function esErrorAutenticacion(): bool
    {
        return $this->status === 401;
    }
}
