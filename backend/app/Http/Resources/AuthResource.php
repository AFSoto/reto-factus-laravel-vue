<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de respuesta al iniciar sesión.
 *
 * Recibe el array ['user' => User, 'token' => string]
 * retornado por AuthService::login() y lo transforma al formato JSON del API.
 */
class AuthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user'  => UserResource::make($this->resource['user']),
            'token' => $this->resource['token'],
        ];
    }
}
