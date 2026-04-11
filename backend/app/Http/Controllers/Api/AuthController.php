<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de autenticación con Sanctum.
 *
 * Rutas:
 *   POST /api/login   → login()   (público)
 *   POST /api/logout  → logout()  (requiere auth)
 *   GET  /api/me      → me()      (requiere auth)
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Autentica al usuario y retorna el token de acceso.
     *
     * @return JsonResponse  { user: {...}, token: "..." }
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return AuthResource::make($result)->response();
    }

    /**
     * Revoca el token actual del usuario autenticado.
     *
     * @return JsonResponse  { message: "..." }
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Retorna los datos del usuario autenticado.
     *
     * @return JsonResponse  { data: { id, name, email, ... } }
     */
    public function me(Request $request): JsonResponse
    {
        return UserResource::make($request->user())->response();
    }
}
