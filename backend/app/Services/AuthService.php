<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Servicio de autenticación con Sanctum.
 *
 * Responsabilidades:
 *   - login():  Validar credenciales y emitir un Personal Access Token.
 *   - logout(): Revocar el token actual del usuario.
 *
 * No tiene dependencias de repositorios — interactúa directamente con
 * el modelo User y la fachada Auth de Laravel.
 */

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Autentica al usuario con email y password.
     * Si las credenciales son válidas emite un Sanctum token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     * @throws ValidationException  Si las credenciales son incorrectas
     */
    public function login(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son válidas.'],
            ]);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('api-token')->plainTextToken;

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    /**
     * Revoca el token de acceso actual del usuario autenticado.
     *
     * @param  User  $user  Usuario autenticado cuyo token se revocará
     * @return void
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
