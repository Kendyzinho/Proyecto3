<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Datos para generar el acuerdo de colaboración que se manda a firmar
class BorradorAcuerdoRequest extends FormRequest
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
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino' => ['required', 'date', 'after_or_equal:fecha_inicio'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Elige el tipo de acuerdo.',
            'representante_legal.required' => 'Ingresa el representante legal.',
            'fecha_inicio.required' => 'Ingresa el inicio de la vigencia.',
            'fecha_termino.required' => 'Ingresa el término de la vigencia.',
            'fecha_termino.after_or_equal' => 'El término no puede ser antes del inicio.',
        ];
    }
}
