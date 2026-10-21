<?php

namespace App\Services;

use App\Models\CentroPractica;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Practica;
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
                ->map(fn ($h) => ['id' => $h->id, 'estudiante' => $h->estudiante->usuario->name, 'tipo_practica' => $h->tipoPractica->nombre]),
            'supervisores' => Supervisor::with('centro')->whereNull('fecha_baja')
                ->whereHas('centro', fn ($q) => $q->whereNull('fecha_baja'))->orderBy('nombre')->get()
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
            if ($supervisor->fecha_baja !== null || $centro->fecha_baja !== null || ! $centro->puedeRecibirEstudiantes()) {
                throw ValidationException::withMessages(['supervisor_id' => 'El supervisor o su centro ya no están disponibles; se requiere un acuerdo vigente.']);
            }

            return Practica::create([
                'habilitacion_id' => $habilitacion->id,
                'supervisor_id' => $supervisor->id,
                'origen' => 'excepcional',
                'modalidad' => $datos['modalidad'],
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'],
                'justificacion_excepcional' => trim($datos['justificacion_excepcional']),
                'registrada_por' => $autora->id,
            ]);
        }, 3);
    }
}
