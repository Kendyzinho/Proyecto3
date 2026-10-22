<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarPracticaExcepcionalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->rol === 'coordinadora' && $this->user()->fecha_baja === null;
    }

    public function rules(): array
    {
        return [
            'periodo_id' => ['required', 'integer', 'exists:periodos,id'],
            'habilitacion_id' => ['required', 'integer', 'exists:habilitaciones,id'],
            'supervisor_id' => ['required', 'integer', 'exists:supervisores,id'],
            'modalidad' => ['required', 'in:presencial,mixta'],
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'justificacion_excepcional' => ['required', 'string', 'max:5000', 'regex:/\S/u'],
            'centro_id' => ['prohibited'],
            'origen' => ['prohibited'],
            'registrada_por' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'exists' => 'La opción seleccionada ya no está disponible.',
            'fecha_fin.after_or_equal' => 'La fecha de término debe ser igual o posterior al inicio.',
            'date_format' => 'Introduce una fecha válida.',
            'modalidad.in' => 'Selecciona una modalidad válida.',
            'justificacion_excepcional.regex' => 'Escribe una justificación, no solo espacios.',
            'justificacion_excepcional.max' => 'La justificación admite hasta 5000 caracteres.',
            'prohibited' => 'Este valor lo determina el servidor.',
        ];
    }
}
