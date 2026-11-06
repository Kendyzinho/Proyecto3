<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// HU-05: una fila de la pantalla Usuarios
class CuentaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rut' => $this->rut,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $this->rol ?? 'estudiante',
            'anio_ingreso' => $this->estudiante?->anio_ingreso,
            'activo' => $this->fecha_baja === null,
            'fecha_baja' => $this->fecha_baja?->toDateString(),
            'motivo_baja' => $this->motivo_baja,
        ];
    }
}
