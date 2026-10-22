<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class CambiarContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'regex:/\p{Lu}/u', 'regex:/[0-9]/',
                'not_regex:/\x00/', 'confirmed', 'different:current_password',
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
            'current_password.required' => 'Ingresa tu contraseña actual.',
            'current_password.string' => 'La contraseña actual debe ser texto.',
            'password.required' => 'Ingresa la nueva contraseña.',
            'password.string' => 'La nueva contraseña debe ser texto.',
            'password.min' => 'Usa al menos 8 caracteres.',
            'password.regex' => 'Incluye al menos una letra mayúscula y un número.',
            'password.not_regex' => 'La nueva contraseña contiene un carácter no permitido.',
            'password.confirmed' => 'La confirmación no coincide con la nueva contraseña.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
            'password_confirmation.required' => 'Repite la nueva contraseña.',
            'password_confirmation.string' => 'La confirmación debe ser texto.',
        ];
    }
}
