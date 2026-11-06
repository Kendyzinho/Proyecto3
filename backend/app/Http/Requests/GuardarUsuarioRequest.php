<?php

namespace App\Http\Requests;

use App\Services\NominaService;
use App\Support\Rut;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// HU-05: alta y edición de una cuenta. El estudiante necesita RUT, correo @alumnos.uta.cl y año de ingreso
class GuardarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // RUT como 12.345.678-9 y correo en minúsculas, para compararlos con lo guardado
    protected function prepareForValidation(): void
    {
        $rut = $this->filled('rut') ? (string) $this->input('rut') : null;

        $this->merge([
            'rut' => $rut === null ? null : (Rut::normalizar($rut) ?? $rut),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario'); // null al crear
        $esEstudiante = $this->input('rol') === 'estudiante';

        return [
            'rol' => ['required', Rule::in(['estudiante', 'secretaria', 'coordinadora', 'jefe'])],
            'name' => ['required', 'string', 'max:255'],
            'rut' => [
                'bail',
                Rule::requiredIf($esEstudiante),
                'nullable',
                'string',
                'max:15',
                function (string $atributo, mixed $valor, Closure $fallar) {
                    if (Rut::normalizar((string) $valor) === null) {
                        $fallar('El RUT no es válido.');
                    }
                },
                Rule::unique('users', 'rut')->ignore($usuario?->id),
            ],
            'email' => [
                'bail',
                'required',
                'email',
                'max:255',
                Rule::when($esEstudiante, ['ends_with:'.NominaService::DOMINIO_ESTUDIANTES]),
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            'anio_ingreso' => [Rule::requiredIf($esEstudiante), 'nullable', 'integer', 'between:1990,'.now()->year],
        ];
    }

    public function messages(): array
    {
        return [
            'rol.required' => 'Elige el rol.',
            'rol.in' => 'El rol no es válido.',
            'name.required' => 'Ingresa el nombre.',
            'rut.required' => 'Ingresa el RUT.',
            'rut.unique' => 'El RUT pertenece a otra cuenta.',
            'email.required' => 'Ingresa el correo.',
            'email.email' => 'El correo no es válido.',
            'email.ends_with' => 'El correo del estudiante debe terminar en @alumnos.uta.cl.',
            'email.unique' => 'El correo pertenece a otra cuenta.',
            'anio_ingreso.required' => 'Ingresa el año de ingreso.',
            'anio_ingreso.between' => 'El año de ingreso no es válido.',
        ];
    }
}
