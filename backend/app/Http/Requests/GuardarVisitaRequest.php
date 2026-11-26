<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// HU-15: fecha, hora, modalidad y prácticas que se revisan en la visita
class GuardarVisitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige el rol de Coordinadora o Jefe de Carrera
    }

    public function rules(): array
    {
        return [
            'fecha_hora' => ['required', 'date_format:Y-m-d H:i'],
            'modalidad' => ['required', Rule::in(['terreno', 'virtual'])],
            'practicas' => ['required', 'array', 'min:1'],
            'practicas.*' => ['required', 'integer', 'distinct', 'exists:practicas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'fecha_hora.date_format' => 'Indica una fecha y hora válidas.',
            'modalidad.in' => 'Elige terreno o virtual.',
            'practicas.min' => 'Elige al menos una práctica.',
            'practicas.*.distinct' => 'No repitas una práctica.',
            'practicas.*.exists' => 'Una de las prácticas no existe.',
            'practicas.*.integer' => 'Una de las prácticas no es válida.',
        ];
    }
}
