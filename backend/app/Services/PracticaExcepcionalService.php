<?php

namespace App\Services;

use App\Models\CentroPractica;
use App\Models\Habilitacion;
use App\Models\HistorialPractica;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\RequerimientoCentro;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PracticaExcepcionalService
{
    public function catalogo(Periodo $periodo): array
    {
        return [
            'periodo' => ['id' => $periodo->id, 'nombre' => $periodo->nombre()],
            'habilitaciones' => Habilitacion::with(['estudiante.usuario', 'tipoPractica'])
                ->where('periodo_id', $periodo->id)->where('cumple_prerrequisito', true)
                ->whereHas('estudiante.usuario', fn ($q) => $q->whereNull('fecha_baja'))
                ->whereDoesntHave('practicas')->get()->sortBy('estudiante.usuario.name')->values()
                ->map(fn ($h) => ['id' => $h->id, 'estudiante' => $h->estudiante->usuario->name, 'tipo_practica' => $h->tipoPractica->nombre, 'horas' => $h->horas_requeridas]),
            'supervisores' => Supervisor::with('centro')->whereNull('fecha_baja')
                ->whereNotNull('fecha_alta')->whereDate('fecha_alta', '<=', today())
                ->whereHas('centro', fn ($q) => $q->whereNull('fecha_baja')->whereNotNull('fecha_alta')->whereDate('fecha_alta', '<=', today()))
                ->orderBy('nombre')->get()
                ->filter(fn ($s) => $s->centro->puedeRecibirEstudiantes())->values()
                ->map(fn ($s) => ['id' => $s->id, 'nombre' => $s->nombre, 'centro' => $s->centro->nombre]),
        ];
    }

    public function registrar(array $datos, User $autora): Practica
    {
        return DB::transaction(function () use ($datos, $autora) {
            // Serializa los registros de HU-11 para una misma habilitación.
            $habilitacion = Habilitacion::lockForUpdate()->findOrFail($datos['habilitacion_id']);
            if ($habilitacion->periodo_id !== (int) $datos['periodo_id'] || ! $habilitacion->cumple_prerrequisito
                || $habilitacion->estudiante->usuario->fecha_baja !== null) {
                throw ValidationException::withMessages(['habilitacion_id' => 'El estudiante no está habilitado para este período.']);
            }
            // No hay un estado fiable de cancelación/finalización: cualquier práctica previa impide otra.
            if ($habilitacion->practicas()->exists()) {
                throw ValidationException::withMessages(['habilitacion_id' => 'Esta habilitación ya tiene una práctica registrada.']);
            }
            $supervisor = Supervisor::lockForUpdate()->findOrFail($datos['supervisor_id']);
            $centro = CentroPractica::lockForUpdate()->findOrFail($supervisor->centro_id);
            if (! $this->estaActivo($supervisor) || ! $this->estaActivo($centro) || ! $centro->puedeRecibirEstudiantes()) {
                throw ValidationException::withMessages(['supervisor_id' => 'El supervisor o su centro ya no están disponibles; se requiere un acuerdo vigente.']);
            }

            if (empty($datos['exceder_cupo'])) {
                $this->avisarSiNoHayCupo($habilitacion, $centro);
            }

            $practica = Practica::create([
                'habilitacion_id' => $habilitacion->id,
                'supervisor_id' => $supervisor->id,
                'origen' => 'excepcional',
                'modalidad' => $datos['modalidad'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'justificacion_excepcional' => trim($datos['justificacion_excepcional']),
                'registrada_por' => $autora->id,
            ]);

            // Sin este registro la práctica no tendría estado: no ocuparía cupo ni sacaría al estudiante de la HU-08
            HistorialPractica::create([
                'practica_id' => $practica->id,
                'user_id' => $autora->id,
                'fecha_hora' => now(),
                'estado_nuevo' => 'confirmada',
                'motivo' => null,
            ]);

            return $practica;
        }, 3);
    }

    // HU-11 puede pasarse del cupo para regularizar un caso especial, pero la Coordinadora debe confirmarlo
    private function avisarSiNoHayCupo(Habilitacion $habilitacion, CentroPractica $centro): void
    {
        $categoria = $habilitacion->tipoPractica->categoria;
        $requerimiento = RequerimientoCentro::where('centro_id', $centro->id)
            ->where('periodo_id', $habilitacion->periodo_id)->where('categoria', $categoria)->first();

        if (! $requerimiento) {
            throw ValidationException::withMessages(['exceder_cupo' => "Este centro no tiene cupos de práctica {$categoria} definidos en este período."]);
        }

        $ocupados = Practica::formalizadas()
            ->whereHas('supervisor', fn ($q) => $q->where('centro_id', $centro->id))
            ->whereHas('habilitacion', fn ($q) => $q->where('periodo_id', $habilitacion->periodo_id)
                ->whereHas('tipoPractica', fn ($tipo) => $tipo->where('categoria', $categoria)))
            ->count();

        if ($ocupados >= $requerimiento->cupos) {
            throw ValidationException::withMessages(['exceder_cupo' => "Este centro no tiene cupos libres de práctica {$categoria} en este período: {$ocupados} ocupados de {$requerimiento->cupos}."]);
        }
    }

    private function estaActivo(Supervisor|CentroPractica $registro): bool
    {
        return $registro->fecha_baja === null && $registro->fecha_alta !== null && ! $registro->fecha_alta->isFuture();
    }
}
