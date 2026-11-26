<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// HU-14: una fila del panel de alertas
class AlertaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $practica = $this->practica;

        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'fecha_referencia' => $this->fecha_referencia?->toDateString(),
            'fecha_generada' => $this->fecha_generada->toIso8601String(),
            'atendida' => $this->fecha_atencion !== null,
            'fecha_atencion' => $this->fecha_atencion?->toIso8601String(),
            'atendida_por' => $this->coordinadora?->name,
            'practica' => [
                'id' => $practica->id,
                'estudiante' => $practica->habilitacion->estudiante->usuario->name,
                'correo' => $practica->habilitacion->estudiante->usuario->email,
                'tipo_practica' => $practica->habilitacion->tipoPractica->nombre,
                'centro' => $practica->supervisor?->centro?->nombre,
                'supervisor' => $practica->supervisor?->nombre,
            ],
        ];
    }
}
