<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-07: carga individual de un estudiante. El RUT, el correo y la práctica los revisa NominaService
class AgregarEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo_id' => ['required', 'integer', 'exists:periodos,id'],
            'rut' => ['required', 'string', 'max:15'],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'string', 'max:255'],
            'anio_ingreso' => ['required', 'integer'],
            'tipo_practica_id' => ['required', 'integer'],
            'cumple_prerrequisito' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo_id.required' => 'Elige el período.',
            'rut.required' => 'Ingresa el RUT.',
            'nombre.required' => 'Ingresa el nombre.',
            'correo.required' => 'Ingresa el correo.',
            'anio_ingreso.required' => 'Ingresa el año de ingreso.',
            'anio_ingreso.integer' => 'El año de ingreso no es válido.',
            'tipo_practica_id.required' => 'Elige la práctica.',
            'cumple_prerrequisito.required' => 'Indica si cumple el prerrequisito.',
        ];
    }
}
