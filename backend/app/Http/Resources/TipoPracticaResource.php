<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// HU-06: una fila de la tabla de prácticas de una malla
class TipoPracticaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'malla_id' => $this->malla_id,
            'nombre' => $this->nombre,
            'categoria' => $this->categoria,
            'horas_requeridas' => $this->horas_requeridas,
            'prerrequisito' => $this->prerrequisito,
        ];
    }
}
