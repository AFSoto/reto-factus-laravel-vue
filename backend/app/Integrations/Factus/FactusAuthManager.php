<?php

declare(strict_types=1);

namespace App\Integrations\Factus;

/**
 * Gestor del ciclo de vida del token OAuth2 de Factus.
 *
 * Implementa el flujo OAuth2 Password Grant para obtener y renovar el
 * access token de la API de Factus. Persiste el token en la base de datos
 * mediante el modelo FactusToken para reutilizarlo entre requests.
 *
 * Flujo de obtención de token:
 *   1. Consulta FactusToken::obtenerVigente() — si hay token válido lo reutiliza.
 *   2. Si no hay token vigente, llama a POST /oauth/token con las credenciales.
 *   3. Persiste el nuevo token con su fecha de expiración.
 *   4. Retorna el access token como string listo para el header Authorization.
 *
 * Este manager es registrado como SINGLETON en el contenedor para que
 * toda la aplicación comparta el mismo token durante el ciclo de vida del request.
 */

use App\Exceptions\FactusApiException;
use App\Models\FactusToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;

class FactusAuthManager
{
    /** URL del endpoint de autenticación OAuth2 de Factus */
    private const TOKEN_ENDPOINT = '/oauth/token';

    /** Guzzle HTTP client dedicado exclusivamente para autenticación */
    private readonly Client $httpClient;

    /**
     * @param  array  $config  Configuración de Factus desde config('services.factus')
     */
    public function __construct(private readonly array $config)
    {
        $this->httpClient = new Client([
            'base_uri' => rtrim($this->config['base_url'], '/'),
            'timeout'  => (int) ($this->config['timeout'] ?? 30),
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    // ── API pública ───────────────────────────────────────────────────────────

    /**
     * Obtiene un access token válido para usar en las peticiones a Factus.
     *
     * Reutiliza el token persistido si aún está vigente.
     * Solicita uno nuevo a la API si no existe o está vencido.
     *
     * @return string  El access token JWT listo para usar en el header Authorization
     * @throws FactusApiException  Si la autenticación falla o hay error de red
     */
    public function obtenerAccessToken(): string
    {
        // Intentar reutilizar un token vigente de la base de datos
        $tokenVigente = FactusToken::obtenerVigente();

        if ($tokenVigente !== null) {
            return $tokenVigente->access_token;
        }

        // No hay token vigente — solicitar uno nuevo a Factus
        $nuevoToken = $this->solicitarNuevoToken();

        return $nuevoToken->access_token;
    }

    /**
     * Fuerza la renovación del token descartando cualquier token existente.
     * Usado por FactusClient cuando recibe un 401 para reintentar la operación.
     *
     * @return string  El nuevo access token
     * @throws FactusApiException  Si la autenticación falla
     */
    public function renovarToken(): string
    {
        $nuevoToken = $this->solicitarNuevoToken();

        return $nuevoToken->access_token;
    }

    /**
     * Verifica si existe un token válido en la base de datos.
     * Útil para el widget de estado de conexión en el dashboard.
     *
     * @return bool
     */
    public function estaConectado(): bool
    {
        return FactusToken::obtenerVigente() !== null;
    }

    // ── Métodos privados ──────────────────────────────────────────────────────

    /**
     * Realiza el POST /oauth/token para obtener un nuevo access token.
     * Persiste el token recibido en la base de datos con su expiración.
     *
     * @return FactusToken  La instancia del token persistido
     * @throws FactusApiException  Si las credenciales son incorrectas o hay error de red
     */
    private function solicitarNuevoToken(): FactusToken
    {
        try {
            $response = $this->httpClient->post(self::TOKEN_ENDPOINT, [
                'json' => [
                    'grant_type'    => 'password',
                    'client_id'     => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'username'      => $this->config['username'],
                    'password'      => $this->config['password'],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Validar que la respuesta tenga el access_token requerido
            if (empty($data['access_token'])) {
                throw FactusApiException::respuestaInvalida(
                    'La respuesta de autenticación no contiene access_token.'
                );
            }

            // Persistir el token con su tiempo de expiración
            return FactusToken::crearDesdeRespuesta($data);

        } catch (ConnectException $e) {
            // Error de red — no fue posible conectar con Factus
            throw FactusApiException::sinConexion($e);

        } catch (RequestException $e) {
            // Respuesta HTTP con error (4xx o 5xx)
            $statusCode   = $e->getResponse()?->getStatusCode() ?? 0;
            $responseBody = $e->getResponse()?->getBody()->getContents() ?? '{}';
            $responseData = json_decode($responseBody, true) ?? [];

            throw FactusApiException::fromResponse($statusCode, $responseData);
        }
    }
}
