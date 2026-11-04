<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarCalendarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige el rol de Coordinadora
    }

    public function rules(): array
    {
        return [
            'fecha_inicio' => ['required', 'date_format:Y-m-d'],
            'fecha_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'dias' => ['required', 'array', 'min:1'],
            'dias.*' => ['required', 'integer', 'between:1,7', 'distinct'],
            'hora_desde' => ['required', 'date_format:H:i'],
            'hora_hasta' => ['required', 'date_format:H:i', 'after:hora_desde'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'date_format' => 'Introduce un valor válido.',
            'fecha_fin.after_or_equal' => 'La fecha de término debe ser igual o posterior al inicio.',
            'dias.min' => 'Selecciona al menos un día.',
            'dias.*.between' => 'Selecciona un día válido.',
            'dias.*.distinct' => 'No repitas un día.',
            'hora_hasta.after' => 'La hora de término debe ser posterior a la de inicio.',
        ];
    }
}
