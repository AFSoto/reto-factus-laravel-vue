<?php

declare(strict_types=1);

namespace App\Providers;

/**
 * AppServiceProvider — proveedor principal de la aplicación.
 *
 * Responsabilidades:
 *   1. Registrar los bindings de Interfaces → Implementaciones concretas
 *      para que Laravel los resuelva automáticamente vía inyección de dependencias.
 *   2. Cualquier configuración global de la aplicación que no pertenezca
 *      a un proveedor especializado.
 *
 * Convención de bindings:
 *   Siempre se registra la Interfaz (Contract) como clave y la implementación
 *   concreta como valor. Los Services declaran el tipo de la interfaz en su
 *   constructor, y el contenedor inyecta la implementación registrada aquí.
 */

use App\Repositories\ClienteRepository;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use App\Repositories\Contracts\FacturaRepositoryInterface;
use App\Repositories\Contracts\ProductoRepositoryInterface;
use App\Repositories\FacturaRepository;
use App\Repositories\ProductoRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los bindings de interfaces en el contenedor de dependencias.
     *
     * Cada bind() le dice a Laravel: "cuando alguien pida la interfaz X,
     * entrega una instancia de la clase Y".
     *
     * @return void
     */
    public function register(): void
    {
        // ── Repositorio de clientes ───────────────────────────────────────────
        $this->app->bind(
            ClienteRepositoryInterface::class,
            ClienteRepository::class
        );

        // ── Repositorio de productos ──────────────────────────────────────────
        $this->app->bind(
            ProductoRepositoryInterface::class,
            ProductoRepository::class
        );

        // ── Repositorio de facturas ───────────────────────────────────────────
        $this->app->bind(
            FacturaRepositoryInterface::class,
            FacturaRepository::class
        );
    }

    /**
     * Configura servicios que requieren que la aplicación esté bootstrapped.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
