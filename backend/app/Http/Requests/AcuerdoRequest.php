<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-10: acuerdo de colaboración del centro. Sin fecha de inicio queda en trámite
class AcuerdoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:tradicional,con_equipamiento'],
            'representante_legal' => ['required', 'string', 'max:255'],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_termino' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'archivo' => ['nullable', 'file', 'extensions:pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Elige el tipo de acuerdo.',
            'representante_legal.required' => 'Ingresa el representante legal.',
            'fecha_termino.after_or_equal' => 'El término no puede ser antes del inicio.',
            'archivo.extensions' => 'El acuerdo debe ser un PDF.',
            'archivo.max' => 'El PDF no puede pesar más de 10 MB.',
        ];
    }
}
