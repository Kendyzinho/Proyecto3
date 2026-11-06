<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

// HU-02: misma política de complejidad que el cambio de contraseña de la HU-04
class RestablecerContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'regex:/\p{Lu}/u', 'regex:/[0-9]/',
                'not_regex:/\x00/', 'confirmed',
                // Bcrypt solo distingue los primeros 72 bytes; se evita el truncamiento silencioso.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen($value) > 72) {
                        $fail('La nueva contraseña es demasiado larga. Usa una más corta.');
                    }
                },
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'El enlace no es válido. Pide uno nuevo.',
            'email.required' => 'El enlace no es válido. Pide uno nuevo.',
            'email.email' => 'El enlace no es válido. Pide uno nuevo.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.string' => 'La nueva contraseña debe ser texto.',
            'password.min' => 'Usa al menos 8 caracteres.',
            'password.regex' => 'Incluye al menos una letra mayúscula y un número.',
            'password.not_regex' => 'La nueva contraseña contiene un carácter no permitido.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password_confirmation.required' => 'Repite la nueva contraseña.',
            'password_confirmation.string' => 'La confirmación debe ser texto.',
        ];
    }
}
