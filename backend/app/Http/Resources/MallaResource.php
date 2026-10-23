<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// una tarjeta de la pantalla mallas y practicas
class MallaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'vigente_desde' => $this->vigente_desde?->toDateString(),
            'estado' => $this->estado(),
            'practicas' => TipoPracticaResource::collection($this->whenLoaded('tiposPractica')),
        ];
    }
}
