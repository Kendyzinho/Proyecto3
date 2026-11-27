<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// HU-14: una alerta en la ficha del estudiante (HU-17)
class AlertaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'fecha_referencia' => $this->fecha_referencia?->toDateString(),
            'fecha_generada' => $this->fecha_generada->toIso8601String(),
            'fecha_atencion' => $this->fecha_atencion?->toIso8601String(),
            'atendida_por' => $this->coordinadora?->name,
            'nota' => $this->nota_atencion,
            'fecha_resolucion' => $this->fecha_resolucion?->toIso8601String(),
        ];
    }
}
