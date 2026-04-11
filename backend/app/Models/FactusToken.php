<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo FactusToken — token OAuth2 de la API de Factus.
 *
 * Persiste el access token obtenido mediante el flujo OAuth2 Password Grant
 * de la API de Factus. El FactusAuthService consulta este modelo para:
 *
 *  1. Verificar si existe un token vigente antes de hacer login.
 *  2. Reutilizar el token activo en todas las peticiones a Factus.
 *  3. Detectar vencimiento y solicitar uno nuevo automáticamente.
 *
 * Diseño de un único token activo:
 *   La tabla puede tener múltiples registros, pero el sistema siempre usa
 *   el más reciente (created_at DESC). Los registros expirados se pueden
 *   limpiar periódicamente con el comando artisan que se creará más adelante.
 *
 * Métodos estáticos:
 *   - obtenerVigente(): retorna el token activo o null si no existe/expiró
 */

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FactusToken extends Model
{
    // ── Configuración del modelo ──────────────────────────────────────────────

    protected $table = 'factus_tokens';

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'access_token',
        'token_type',
        'expires_in',
        'expires_at',
        'refresh_token',
    ];

    // ── Métodos estáticos de acceso ───────────────────────────────────────────

    /**
     * Retorna el token de acceso más reciente que aún no ha expirado.
     *
     * Aplica un margen de 60 segundos antes del vencimiento para evitar
     * condiciones de carrera donde el token expira durante una petición.
     *
     * @return static|null Token vigente o null si no existe o todos expiraron
     */
    public static function obtenerVigente(): ?static
    {
        return static::query()
            ->vigentes()
            ->latest()
            ->first();
    }

    /**
     * Crea y persiste un nuevo token a partir de la respuesta de Factus.
     *
     * Recibe el array retornado por el endpoint /oauth/token y calcula
     * automáticamente la fecha exacta de expiración.
     *
     * @param  array{access_token: string, token_type: string, expires_in: int, refresh_token?: string}  $respuesta
     *         Array con los campos retornados por la API de Factus
     * @return static El token recién creado y persistido
     */
    public static function crearDesdeRespuesta(array $respuesta): static
    {
        return static::create([
            'access_token'  => $respuesta['access_token'],
            'token_type'    => $respuesta['token_type'] ?? 'Bearer',
            'expires_in'    => $respuesta['expires_in'],
            'expires_at'    => Carbon::now()->addSeconds((int) $respuesta['expires_in']),
            'refresh_token' => $respuesta['refresh_token'] ?? null,
        ]);
    }

    // ── Métodos de instancia ──────────────────────────────────────────────────

    /**
     * Indica si este token sigue siendo válido para uso inmediato.
     *
     * Aplica un margen de 60 segundos para evitar usar tokens
     * que están a punto de vencer.
     *
     * @return bool True si el token aún no ha expirado con el margen de seguridad
     */
    public function estaVigente(): bool
    {
        return Carbon::now()->isBefore(
            $this->expires_at->subSeconds(60)
        );
    }

    /**
     * Retorna el token con el tipo incluido para el header Authorization.
     *
     * @return string Ej: "Bearer eyJhbGciOiJSUz..."
     */
    public function headerAuthorization(): string
    {
        return "{$this->token_type} {$this->access_token}";
    }

    // ── Query Scopes ──────────────────────────────────────────────────────────

    /**
     * Filtra tokens que aún no han expirado (con margen de 60 segundos).
     *
     * @param  Builder<FactusToken>  $query
     * @return Builder<FactusToken>
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where(
            'expires_at',
            '>',
            Carbon::now()->addSeconds(60)
        );
    }

    /**
     * Filtra tokens que ya han expirado — útil para limpieza.
     *
     * @param  Builder<FactusToken>  $query
     * @return Builder<FactusToken>
     */
    public function scopeExpirados(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', Carbon::now());
    }

    // ── Casts ─────────────────────────────────────────────────────────────────

    /**
     * Conversión de atributos a tipos nativos de PHP.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_in' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
