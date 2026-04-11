<?php

declare(strict_types=1);

namespace App\Integrations\Factus;

/**
 * Cliente HTTP para la API de Factus.
 *
 * Encapsula todas las llamadas HTTP a la API de Factus con:
 *   - Autenticación automática: inyecta el Bearer token en cada request
 *   - Reintento transparente: si recibe 401, renueva el token y reintenta UNA vez
 *   - Mapeo de errores: convierte respuestas de error en FactusApiException
 *   - DTOs de respuesta: retorna arrays PHP ya parseados, nunca objetos Guzzle
 *
 * Métodos disponibles:
 *   - get(string $endpoint): array
 *   - post(string $endpoint, array $datos): array
 *   - delete(string $endpoint): array
 *
 * Endpoints principales de la API Factus:
 *   - POST /oauth/token                          → Autenticación (maneja FactusAuthManager)
 *   - GET  /v1/numbering-ranges                  → Rangos de numeración disponibles
 *   - POST /v1/bills/validate                    → Emitir factura electrónica
 *   - GET  /v1/bills/{number}/download-pdf       → Descargar PDF de factura
 *   - POST /v1/bills/cancel/{number}             → Anular factura
 *
 * Este cliente es registrado como SINGLETON en AppServiceProvider.
 */

use App\Exceptions\FactusApiException;
use App\Integrations\DTOs\FactusErrorDTO;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class FactusClient
{
    /** Guzzle HTTP client base */
    private readonly Client $httpClient;

    /**
     * @param  FactusAuthManager  $authManager  Gestor del token OAuth2
     * @param  array              $config       Configuración desde config('services.factus')
     */
    public function __construct(
        private readonly FactusAuthManager $authManager,
        private readonly array $config,
    ) {
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->config['base_url'], '/'),
            'timeout'  => (int) ($this->config['timeout'] ?? 30),
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // ── Métodos HTTP públicos ─────────────────────────────────────────────────

    /**
     * Realiza una petición GET a la API de Factus.
     *
     * @param  string  $endpoint  Ruta del endpoint (ej: '/v1/numbering-ranges')
     * @param  array   $query     Query parameters opcionales
     * @return array  Payload JSON de la respuesta parseado
     * @throws FactusApiException  Si la API retorna error o hay problema de red
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->ejecutarConReintento('GET', $endpoint, ['query' => $query]);
    }

    /**
     * Realiza una petición POST a la API de Factus con cuerpo JSON.
     *
     * @param  string  $endpoint  Ruta del endpoint (ej: '/v1/bills/validate')
     * @param  array   $datos     Cuerpo de la petición que se serializa a JSON
     * @return array  Payload JSON de la respuesta parseado
     * @throws FactusApiException  Si la API retorna error o hay problema de red
     */
    public function post(string $endpoint, array $datos = []): array
    {
        return $this->ejecutarConReintento('POST', $endpoint, ['json' => $datos]);
    }

    /**
     * Realiza una petición DELETE a la API de Factus.
     *
     * @param  string  $endpoint  Ruta del endpoint
     * @return array  Payload JSON de la respuesta parseado
     * @throws FactusApiException  Si la API retorna error o hay problema de red
     */
    public function delete(string $endpoint): array
    {
        return $this->ejecutarConReintento('DELETE', $endpoint);
    }

    // ── Métodos de negocio específicos de Factus ──────────────────────────────

    /**
     * Obtiene los rangos de numeración disponibles para emitir facturas.
     * El frontend muestra estos rangos al crear una factura nueva.
     *
     * @return array  Array de rangos de numeración retornados por Factus
     * @throws FactusApiException
     */
    public function obtenerRangosNumeracion(): array
    {
        $response = $this->get('/v1/numbering-ranges');

        return $response['data'] ?? $response;
    }

    /**
     * Emite una factura electrónica enviándola a la DIAN a través de Factus.
     *
     * @param  array  $payload  Datos de la factura en el formato requerido por Factus
     * @return array  Respuesta completa de Factus con id, cufe, qr, pdf, etc.
     * @throws FactusApiException
     */
    public function emitirFactura(array $payload): array
    {
        return $this->post('/v1/bills/validate', $payload);
    }

    /**
     * Descarga el PDF de una factura emitida.
     *
     * @param  string  $numero  Número de factura (ej: "SETP990000001")
     * @return array  Respuesta con el PDF en Base64
     * @throws FactusApiException
     */
    public function descargarPdf(string $numero): array
    {
        return $this->get("/v1/bills/download-pdf/{$numero}");
    }

    /**
     * Anula una factura previamente emitida ante la DIAN.
     *
     * @param  string  $numero  Número de factura a anular
     * @return array  Confirmación de anulación de Factus
     * @throws FactusApiException
     */
    public function anularFactura(string $numero): array
    {
        return $this->post("/v1/bills/cancel/{$numero}");
    }

    // ── Motor interno de ejecución ────────────────────────────────────────────

    /**
     * Ejecuta una petición HTTP con autenticación automática y reintento en 401.
     *
     * Flujo:
     *   1. Obtiene el token vigente del FactusAuthManager
     *   2. Ejecuta la petición con el header Authorization
     *   3. Si recibe 401, renueva el token y reintenta UNA vez
     *   4. Si falla en el segundo intento, lanza FactusApiException
     *
     * @param  string  $method    Método HTTP: 'GET', 'POST', 'DELETE'
     * @param  string  $endpoint  Ruta del endpoint
     * @param  array   $opciones  Opciones adicionales para Guzzle (json, query, etc.)
     * @return array  Payload JSON de la respuesta parseado
     * @throws FactusApiException
     */
    private function ejecutarConReintento(
        string $method,
        string $endpoint,
        array $opciones = []
    ): array {
        $token = $this->authManager->obtenerAccessToken();

        try {
            return $this->ejecutarPeticion($method, $endpoint, $token, $opciones);

        } catch (FactusApiException $e) {
            // Si fue un 401, renovar el token y reintentar UNA sola vez
            if ($e->esErrorAutenticacion()) {
                $tokenRenovado = $this->authManager->renovarToken();

                return $this->ejecutarPeticion($method, $endpoint, $tokenRenovado, $opciones);
            }

            // Cualquier otro error se propaga inmediatamente
            throw $e;
        }
    }

    /**
     * Ejecuta la petición HTTP con el token dado.
     * Parsea la respuesta o lanza FactusApiException según el resultado.
     *
     * @param  string  $method    Método HTTP
     * @param  string  $endpoint  Ruta del endpoint
     * @param  string  $token     Access token JWT para el header Authorization
     * @param  array   $opciones  Opciones Guzzle adicionales
     * @return array  Payload JSON de la respuesta
     * @throws FactusApiException
     */
    private function ejecutarPeticion(
        string $method,
        string $endpoint,
        string $token,
        array $opciones = []
    ): array {
        // Inyectamos el token en los headers de esta petición
        $opciones['headers'] = [
            'Authorization' => "Bearer {$token}",
        ];

        try {
            $response = $this->httpClient->request($method, $endpoint, $opciones);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw FactusApiException::respuestaInvalida(
                    'La API retornó una respuesta que no es JSON válido.'
                );
            }

            return $data ?? [];

        } catch (ConnectException $e) {
            throw FactusApiException::sinConexion($e);

        } catch (RequestException $e) {
            $statusCode   = $e->getResponse()?->getStatusCode() ?? 0;
            $responseBody = $e->getResponse()?->getBody()->getContents() ?? '{}';
            $responseData = json_decode($responseBody, true) ?? [];

            // Construir el DTO de error para análisis antes de lanzar la excepción
            $errorDto = FactusErrorDTO::fromResponse($statusCode, $responseData);

            throw FactusApiException::fromResponse(
                $errorDto->status,
                $errorDto->tieneErroresDeCampo() ? $errorDto->errors : $errorDto->raw
            );
        }
    }
}
