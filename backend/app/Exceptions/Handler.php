<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Handler de excepciones de la aplicación.
 *
 * Extiende el handler base de Laravel para interceptar las excepciones del
 * dominio y de la integración Factus, convirtiéndolas en respuestas JSON
 * con el código HTTP apropiado.
 *
 * Mapeo de excepciones → códigos HTTP:
 *   - ClienteNotFoundException     → 404
 *   - FacturaNotFoundException      → 404
 *   - ProductoNotFoundException     → 404
 *   - FacturaYaAnuladaException     → 422
 *   - FacturaNoBorradorException    → 422
 *   - FactusApiException (auth)     → 502
 *   - FactusApiException (validación) → 422
 *   - FactusApiException (otros)    → 502
 *
 * El formato de respuesta JSON sigue la convención del API:
 *   { "message": "...", "errors": {...} }  (errores de validación)
 *   { "message": "..." }                   (otros errores)
 */

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Excepciones que nunca se reportan al log.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [];

    /**
     * Excepciones cuyos campos no se incluyen en la respuesta de validación.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Registra los callbacks de manejo de excepciones del dominio.
     * Cada renderable() intercepta una excepción específica y retorna
     * la respuesta JSON correspondiente.
     *
     * @return void
     */
    public function register(): void
    {
        // ── Excepciones de "no encontrado" → 404 ─────────────────────────────
        $this->renderable(
            fn (ClienteNotFoundException $e) => $this->respuestaError($e->getMessage(), 404)
        );

        $this->renderable(
            fn (FacturaNotFoundException $e) => $this->respuestaError($e->getMessage(), 404)
        );

        $this->renderable(
            fn (ProductoNotFoundException $e) => $this->respuestaError($e->getMessage(), 404)
        );

        // ── Excepciones de reglas de negocio → 422 ───────────────────────────
        $this->renderable(
            fn (FacturaYaAnuladaException $e) => $this->respuestaError($e->getMessage(), 422)
        );

        $this->renderable(
            fn (FacturaNoBorradorException $e) => $this->respuestaError($e->getMessage(), 422)
        );

        // ── Excepciones de la API Factus → 422 o 502 ─────────────────────────
        $this->renderable(function (FactusApiException $e): JsonResponse {
            // Los errores de validación DIAN se reportan con 422 e incluyen detalle
            if ($e->esErrorValidacion()) {
                return $this->respuestaError(
                    mensaje: $e->getMessage(),
                    status:  422,
                    errores: $e->getResponseData(),
                );
            }

            // Cualquier otro error de Factus se reporta como 502 Bad Gateway
            return $this->respuestaError($e->getMessage(), 502);
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Construye una respuesta JSON de error con el formato estándar de la API.
     *
     * @param  string  $mensaje  Mensaje legible del error
     * @param  int     $status   Código HTTP de la respuesta
     * @param  array   $errores  Errores de detalle (validación, campos, etc.)
     * @return JsonResponse
     */
    private function respuestaError(string $mensaje, int $status, array $errores = []): JsonResponse
    {
        $body = ['message' => $mensaje];

        if (!empty($errores)) {
            $body['errors'] = $errores;
        }

        return response()->json($body, $status);
    }
}
