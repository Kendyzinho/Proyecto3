<?php

namespace App\Services;

use App\Mail\RecuperarContrasena;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
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

    // HU-02: solo las cuentas activas reciben el enlace. La respuesta es la misma exista o no el correo,
    // para no revelar qué cuentas hay en el sistema.
    public function enviarEnlaceRecuperacion(string $email): void
    {
        Password::sendResetLink(['email' => $email, 'fecha_baja' => null], function (User $usuario, string $token): void {
            $enlace = rtrim(config('app.frontend_url'), '/').'/auth/restablecer?'.http_build_query([
                'token' => $token,
                'email' => $usuario->email,
            ]);
            Mail::to($usuario)->send(new RecuperarContrasena($usuario, $enlace));
        });
    }

    // HU-02: el token se borra al usarse, así que un enlace vencido o ya usado no cambia la clave.
    // Al cambiarla se cierran todas las sesiones abiertas de la cuenta.
    public function restablecerContrasena(string $token, string $email, #[\SensitiveParameter] string $nueva): void
    {
        $estado = Password::reset(
            ['email' => $email, 'fecha_baja' => null, 'token' => $token, 'password' => $nueva],
            function (User $usuario, string $clave): void {
                $usuario->password = Hash::make($clave);
                $usuario->save();
                $usuario->tokens()->delete();
            },
        );

        if ($estado !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'El enlace no es válido o ya venció. Pide uno nuevo.',
            ]);
        }
    }
}
