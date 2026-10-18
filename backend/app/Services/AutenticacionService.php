<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

// HU-01: solo entran usuarios con credenciales válidas y cuenta activa (fecha_baja vacía)
class AutenticacionService
{
    /**
     * @return array{token: string, usuario: User}
     */
    public function iniciarSesion(string $email, string $password): array
    {
        $usuario = User::where('email', $email)->first();

        if (! $usuario || ! Hash::check($password, $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        if ($usuario->fecha_baja !== null) {
            throw new AccessDeniedHttpException('Tu cuenta está desactivada.');
        }

        return [
            'token' => $usuario->createToken('web')->plainTextToken,
            'usuario' => $usuario,
        ];
    }

    public function cambiarContrasena(User $usuario, #[\SensitiveParameter] string $actual, #[\SensitiveParameter] string $nueva): void
    {
        if (! Hash::check($actual, $usuario->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $usuario->password = Hash::make($nueva);
        $usuario->save();
    }

    // HU-03: invalida el token con que se hizo la petición
    public function cerrarSesion(User $usuario): void
    {
        $usuario->currentAccessToken()->delete();
    }
}
