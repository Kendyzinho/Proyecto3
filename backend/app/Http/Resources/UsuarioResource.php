<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            // En la tabla users el estudiante no tiene rol; para el frontend es 'estudiante'
            'rol' => $this->rol ?? 'estudiante',
        ];
    }
}
