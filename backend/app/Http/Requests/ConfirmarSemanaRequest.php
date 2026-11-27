<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmarSemanaRequest extends FormRequest
{

    public function authorize(): bool
    {
        return (bool) $this->user()?->esSupervisor();
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
