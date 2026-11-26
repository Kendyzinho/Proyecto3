<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// HU-16: respuestas de la pauta de una práctica; con `enviar` en falso se guarda como borrador
class GuardarPautaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige el rol de Coordinadora o Jefe de Carrera
    }

    // La forma se valida aquí para que un dato mal formado dé 422 y no un error del servidor;
    // que cada respuesta calce con su criterio lo revisa InstrumentoService
    public function rules(): array
    {
        return [
            'respuestas' => ['present', 'array'],
            'respuestas.*' => ['array'],
            'respuestas.*.item_id' => ['required', 'integer'],
            'respuestas.*.opcion_id' => ['nullable', 'integer'],
            'respuestas.*.texto' => ['nullable', 'string', 'max:3000'],
            'enviar' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'present' => 'Faltan las respuestas.',
            'respuestas.array' => 'Las respuestas no son válidas.',
            'respuestas.*.array' => 'Una de las respuestas no es válida.',
            'respuestas.*.item_id.integer' => 'Una de las preguntas no es válida.',
            'respuestas.*.opcion_id.integer' => 'Elige una opción válida.',
            'respuestas.*.texto.string' => 'El comentario no es válido.',
            'respuestas.*.texto.max' => 'El comentario no puede superar los 3000 caracteres.',
            'enviar.boolean' => 'No se pudo saber si es borrador o envío.',
        ];
    }
}
