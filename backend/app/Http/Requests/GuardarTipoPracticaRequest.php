<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// HU-06: datos de una práctica de la malla (nombre, categoría, horas y prerrequisito)
class GuardarTipoPracticaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => Str::squish((string) $this->input('nombre')),
            'prerrequisito' => $this->filled('prerrequisito') ? Str::squish((string) $this->input('prerrequisito')) : null,
        ]);
    }

    public function rules(): array
    {
        $tipo = $this->route('tipo'); // null al agregar
        $mallaId = $tipo?->malla_id ?? $this->route('malla')?->id;

        return [
            'nombre' => [
                'required',
                'string',
                'max:60',
                // Dos prácticas de la misma malla no pueden llamarse igual
                Rule::unique('tipos_practica', 'nombre')
                    ->where('malla_id', $mallaId)
                    ->whereNull('fecha_baja')
                    ->ignore($tipo?->id),
            ],
            'categoria' => ['required', Rule::in(['laboral', 'profesional'])],
            'horas_requeridas' => ['required', 'integer', 'between:1,1000'],
            'prerrequisito' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre de la práctica.',
            'nombre.unique' => 'La malla ya tiene una práctica con ese nombre.',
            'categoria.required' => 'Elige la categoría.',
            'categoria.in' => 'La categoría no es válida.',
            'horas_requeridas.required' => 'Ingresa las horas requeridas.',
            'horas_requeridas.integer' => 'Las horas deben ser un número entero.',
            'horas_requeridas.between' => 'Las horas deben estar entre 1 y 1000.',
        ];
    }
}
