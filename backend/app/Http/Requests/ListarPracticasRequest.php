<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListarPracticasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tipo_practica_id' => ['sometimes', 'nullable', 'integer', 'exists:tipos_practica,id'],
            'estado' => ['sometimes', 'nullable', 'in:en_curso,posible_abandono,por_iniciar'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.string' => 'La búsqueda debe ser texto.',
            'q.max' => 'La búsqueda no puede superar los 120 caracteres.',
            'tipo_practica_id.integer' => 'Selecciona una práctica válida.',
            'tipo_practica_id.exists' => 'Selecciona una práctica válida.',
            'estado.in' => 'Selecciona un estado válido.',
        ];
    }
}
