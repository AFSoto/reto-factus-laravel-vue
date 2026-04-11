<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción lanzada cuando la API de Factus retorna un error.
 *
 * Captura el código HTTP de la respuesta y el payload completo para
 * facilitar el diagnóstico. El FactusClient la lanza ante cualquier
 * respuesta con status >= 400.
 *
 * Factory methods disponibles:
 *   - autenticacionFallida()   → 401 al solicitar el token OAuth2
 *   - respuestaInvalida()      → respuesta no parseable o sin estructura esperada
 *   - errorServidor()          → status 500+ del servidor Factus
 *   - errorValidacion()        → status 422 con errores de validación DIAN
 *   - sinConexion()            → timeout o error de red
 *   - fromResponse()           → desde cualquier respuesta HTTP con status >= 400
 */
class FactusApiException extends \RuntimeException
{
    /**
     * @param  string  $message       Mensaje descriptivo del error
     * @param  int     $statusCode    Código HTTP retornado por Factus (0 = error de red)
     * @param  array   $responseData  Payload completo de la respuesta de error
     * @param  \Throwable|null  $previous  Excepción original encadenada
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly array $responseData = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    // ── Factory methods ───────────────────────────────────────────────────────

    /**
     * Error de autenticación OAuth2 — credenciales inválidas o token expirado.
     *
     * @param  array  $responseData  Payload de la respuesta 401
     * @return static
     */
    public static function autenticacionFallida(array $responseData = []): static
    {
        return new static(
            message:      'Autenticación con Factus fallida. Verifique las credenciales configuradas.',
            statusCode:   401,
            responseData: $responseData,
        );
    }

    /**
     * La respuesta de Factus no tiene la estructura JSON esperada.
     *
     * @param  string  $detalle  Descripción del problema de estructura
     * @return static
     */
    public static function respuestaInvalida(string $detalle): static
    {
        return new static(
            message:    "Respuesta inválida de la API Factus: {$detalle}",
            statusCode: 0,
        );
    }

    /**
     * Error interno del servidor de Factus (5xx).
     *
     * @param  int    $statusCode    Código HTTP 5xx
     * @param  array  $responseData  Payload de la respuesta
     * @return static
     */
    public static function errorServidor(int $statusCode, array $responseData = []): static
    {
        return new static(
            message:      "Error interno del servidor Factus (HTTP {$statusCode}). Intente nuevamente.",
            statusCode:   $statusCode,
            responseData: $responseData,
        );
    }

    /**
     * Error de validación — Factus rechazó los datos enviados (422).
     * Incluye los errores de campo retornados por la DIAN / Factus.
     *
     * @param  array  $errores  Array de errores de validación retornados por Factus
     * @return static
     */
    public static function errorValidacion(array $errores): static
    {
        $resumen = implode('; ', array_map(
            fn ($campo, $msgs) => "{$campo}: " . implode(', ', (array) $msgs),
            array_keys($errores),
            $errores
        ));

        return new static(
            message:      "Factus rechazó la solicitud por errores de validación: {$resumen}",
            statusCode:   422,
            responseData: $errores,
        );
    }

    /**
     * Timeout de red o error de conexión — Factus no fue alcanzable.
     *
     * @param  \Throwable  $e  Excepción de red original (GuzzleException, etc.)
     * @return static
     */
    public static function sinConexion(\Throwable $e): static
    {
        return new static(
            message:   'No fue posible conectar con la API de Factus. Verifique su conexión a internet.',
            statusCode: 0,
            previous:   $e,
        );
    }

    /**
     * Construye la excepción a partir de cualquier respuesta HTTP con status >= 400.
     * Selecciona automáticamente el factory method apropiado según el status.
     *
     * @param  int    $statusCode    Código HTTP de la respuesta
     * @param  array  $responseData  Payload JSON de la respuesta de error
     * @return static
     */
    public static function fromResponse(int $statusCode, array $responseData): static
    {
        if ($statusCode === 401) {
            return static::autenticacionFallida($responseData);
        }

        if ($statusCode === 422) {
            $errores = $responseData['errors'] ?? $responseData['message'] ?? $responseData;

            return static::errorValidacion((array) $errores);
        }

        if ($statusCode >= 500) {
            return static::errorServidor($statusCode, $responseData);
        }

        $mensaje = $responseData['message'] ?? "Error HTTP {$statusCode} en la API de Factus";

        return new static(
            message:      $mensaje,
            statusCode:   $statusCode,
            responseData: $responseData,
        );
    }

    // ── Getters ───────────────────────────────────────────────────────────────

    /**
     * Retorna el código HTTP de la respuesta de Factus.
     * 0 indica error de red (sin respuesta HTTP).
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Retorna el payload completo de la respuesta de error de Factus.
     *
     * @return array
     */
    public function getResponseData(): array
    {
        return $this->responseData;
    }

    /**
     * Indica si el error fue de autenticación.
     *
     * @return bool
     */
    public function esErrorAutenticacion(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * Indica si el error fue de validación (datos rechazados por la DIAN).
     *
     * @return bool
     */
    public function esErrorValidacion(): bool
    {
        return $this->statusCode === 422;
    }
}
