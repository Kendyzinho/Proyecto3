<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarSemanaRequest extends FormRequest
{
    // La firma del enlace (middleware signed) ya autoriza al supervisor
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dias' => ['required', 'array', 'min:1'],
            'dias.*.id' => ['required', 'integer', 'distinct'],
            'dias.*.validacion' => ['required', Rule::in(['confirmado', 'rechazado'])],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
