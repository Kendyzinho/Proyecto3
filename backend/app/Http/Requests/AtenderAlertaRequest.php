<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-14: la Coordinadora puede anotar qué hizo al atender la alerta
class AtenderAlertaRequest extends FormRequest
{
    // La ruta ya exige el rol de Coordinadora o Jefe
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nota.max' => 'La nota no puede superar los 500 caracteres.',
        ];
    }
}
