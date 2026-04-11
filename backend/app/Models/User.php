<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Modelo User — usuario autenticado del sistema.
 *
 * Representa al operador que gestiona clientes, productos y facturas.
 * Usa Laravel Sanctum (HasApiTokens) para autenticación stateless
 * mediante tokens de API en lugar de sesiones de cookie.
 *
 * Relaciones:
 *   - clientes: todos los clientes registrados por este usuario
 *   - productos: catálogo de productos de este usuario
 *   - facturas: todas las facturas emitidas por este usuario
 */

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Campos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Campos ocultos en la serialización JSON.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ── Relaciones ────────────────────────────────────────────────────────────

    /**
     * Retorna todos los clientes registrados por este usuario.
     *
     * @return HasMany<Cliente>
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * Retorna todos los productos del catálogo de este usuario.
     *
     * @return HasMany<Producto>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Retorna todos las facturas emitidas por este usuario.
     *
     * @return HasMany<Factura>
     */
    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
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
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
