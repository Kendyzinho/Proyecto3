<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarContrasenaRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Services\AutenticacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AutenticacionService $autenticacion) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $sesion = $this->autenticacion->iniciarSesion($request->string('email'), $request->string('password'));

        return response()->json([
            'token' => $sesion['token'],
            'usuario' => new UsuarioResource($sesion['usuario']),
        ]);
    }

    public function logout(Request $request): Response
    {
        $this->autenticacion->cerrarSesion($request->user());

        return response()->noContent();
    }

    public function me(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user());
    }

    public function cambiarContrasena(CambiarContrasenaRequest $request): Response
    {
        $this->autenticacion->cambiarContrasena(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password'),
        );

        return response()->noContent();
    }
}
