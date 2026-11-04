<?php

namespace App\Services;

use App\Models\Periodo;
use App\Models\Practica;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

// Fechas y horario de una práctica confirmada (Art. 17). HU-14 los usa para saber qué días se espera marca.
class CalendarioPracticaService
{
    private const ESTADOS_EDITABLES = ['confirmada', 'en_curso'];

    public function listar(Periodo $periodo): Collection
    {
        return $this->editables()
            ->with(['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro', 'horarios'])
            ->whereHas('habilitacion', fn ($q) => $q->where('periodo_id', $periodo->id))
            ->get()
            ->map(fn (Practica $practica) => $this->serializar($practica))
            // Primero lo que falta por definir
            ->sortBy(fn (array $fila) => [$fila['horario'] !== null, $fila['estudiante']])
            ->values();
    }

    public function buscarEditable(int $id): Practica
    {
        return $this->editables()->findOrFail($id);
    }

    public function guardar(Practica $practica, array $datos): void
    {
        DB::transaction(function () use ($practica, $datos) {
            $practica->update(['fecha_inicio' => $datos['fecha_inicio'], 'fecha_fin' => $datos['fecha_fin']]);

            // Una vez iniciada, el horario anterior se conserva: dice qué días se esperaba marca hasta el cambio
            $vigenteDesde = max($datos['fecha_inicio'], today()->toDateString());
            $practica->horarios()->whereDate('vigente_desde', '>=', $vigenteDesde)->delete();

            foreach ($datos['dias'] as $dia) {
                $practica->horarios()->create([
                    'dia_semana' => $dia,
                    'hora_inicio' => $datos['hora_desde'],
                    'hora_fin' => $datos['hora_hasta'],
                    'vigente_desde' => $vigenteDesde,
                ]);
            }
        });
    }

    private function editables()
    {
        return Practica::conEstado(self::ESTADOS_EDITABLES);
    }

    private function serializar(Practica $practica): array
    {
        $horario = $practica->horarioVigente();
        $primero = $horario->first();

        return [
            'id' => $practica->id,
            'estudiante' => $practica->habilitacion->estudiante->usuario->name,
            'tipo_practica' => $practica->habilitacion->tipoPractica->nombre,
            'centro' => $practica->supervisor->centro->nombre,
            'fecha_inicio' => $practica->fecha_inicio?->toDateString(),
            'fecha_fin' => $practica->fecha_fin?->toDateString(),
            'horario' => $primero ? [
                'dias' => $horario->pluck('dia_semana')->all(),
                'hora_desde' => substr((string) $primero->hora_inicio, 0, 5),
                'hora_hasta' => substr((string) $primero->hora_fin, 0, 5),
            ] : null,
        ];
    }
}
