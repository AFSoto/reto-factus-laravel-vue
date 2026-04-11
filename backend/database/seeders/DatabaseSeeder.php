<?php

declare(strict_types=1);

namespace Database\Seeders;

/**
 * Seeder principal de FactusPro.
 *
 * Crea el usuario administrador inicial para poder iniciar sesión
 * en la aplicación y probar el flujo completo de facturación.
 *
 * Credenciales de acceso al API:
 *   Email:    admin@factuspro.com
 *   Password: password
 *
 * Ejecución:
 *   php artisan db:seed
 *   php artisan migrate:fresh --seed
 *
 * ⚠️  Cambiar la contraseña en entornos productivos.
 */

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // firstOrCreate garantiza idempotencia: re-ejecutar el seeder no crea duplicados
        User::firstOrCreate(
            ['email' => 'admin@factuspro.com'],
            [
                'name'     => 'Administrador',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info('✓ Usuario admin@factuspro.com creado (password: password)');
    }
}
