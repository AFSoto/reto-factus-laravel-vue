<?php

declare(strict_types=1);

namespace App\Providers;

/**
 * AppServiceProvider — proveedor principal de la aplicación.
 *
 * Responsabilidades:
 *   1. Bindings Interface → Implementación para los Repositories.
 *   2. Singletons para los servicios de integración con Factus:
 *      FactusAuthManager y FactusClient se instancian UNA sola vez por request.
 */

use App\Integrations\Factus\FactusAuthManager;
use App\Integrations\Factus\FactusClient;
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
     * Registra bindings de interfaces y singletons en el contenedor IoC.
     *
     * @return void
     */
    public function register(): void
    {
        // ── Repositorios: Interface → Implementación concreta ─────────────────
        $this->app->bind(
            ClienteRepositoryInterface::class,
            ClienteRepository::class
        );

        $this->app->bind(
            ProductoRepositoryInterface::class,
            ProductoRepository::class
        );

        $this->app->bind(
            FacturaRepositoryInterface::class,
            FacturaRepository::class
        );

        // ── Integración Factus: singletons ────────────────────────────────────
        // FactusAuthManager gestiona el token OAuth2 — singleton para no
        // perder el estado del token entre llamadas dentro del mismo request.
        $this->app->singleton(FactusAuthManager::class, function (): FactusAuthManager {
            return new FactusAuthManager(
                config: config('services.factus')
            );
        });

        // FactusClient depende de FactusAuthManager — también singleton
        // para reutilizar la misma instancia de Guzzle en el request.
        $this->app->singleton(FactusClient::class, function ($app): FactusClient {
            return new FactusClient(
                authManager: $app->make(FactusAuthManager::class),
                config:      config('services.factus'),
            );
        });
    }

    /**
     * Bootstrap de servicios que requieren la app inicializada.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
