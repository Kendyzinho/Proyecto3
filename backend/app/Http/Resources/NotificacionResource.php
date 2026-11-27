<?php

namespace App\Http\Resources;

use App\Models\Alerta;
use App\Services\ConfirmacionSemanalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

// HU-14: una línea de la campana. Recibe ['alerta' => Alerta, 'otras' => cuántas más tiene esa práctica]
class NotificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Alerta $alerta */
        $alerta = $this->resource['alerta'];
        $practica = $alerta->practica;

        return [
            'id' => $alerta->id,
            'tipo' => $alerta->tipo,
            'fecha_referencia' => $alerta->fecha_referencia?->toDateString(),
            'practica_id' => $practica->id,
            'estudiante' => $practica->habilitacion->estudiante->usuario->name,
            'otras' => $this->resource['otras'],
            // El supervisor(a) confirma la semana en la misma página del correo semanal (HU-26)
            'enlace' => $request->user()->esSupervisor() && $alerta->fecha_referencia !== null
                ? app(ConfirmacionSemanalService::class)->enlace($practica, Carbon::parse($alerta->fecha_referencia))
                : null,
        ];
    }
}
