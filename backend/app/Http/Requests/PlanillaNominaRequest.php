<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-07: planilla de la nómina y período al que se carga
class PlanillaNominaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'max:2048', 'extensions:xlsx,csv'],
            'periodo_id' => ['required', 'integer', 'exists:periodos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Sube la planilla.',
            'archivo.max' => 'La planilla no puede pesar más de 2 MB.',
            'archivo.extensions' => 'La planilla debe ser Excel (.xlsx) o CSV.',
            'periodo_id.required' => 'Elige el período.',
            'periodo_id.exists' => 'El período no existe.',
        ];
    }
}
