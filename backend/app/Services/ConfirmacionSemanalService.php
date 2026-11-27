<?php

namespace App\Services;

use App\Mail\ConfirmacionSemanalMail;
use App\Models\ConfirmacionSemanal;
use App\Models\Practica;
use App\Models\RegistroDiario;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

// HU-26: el supervisor entra con su cuenta  y confirma o rechaza cada dia que registro su estudiante (HU-13).
// La alerta por dias sin confirmar la genera la HU-14 .
class ConfirmacionSemanalService
{
    // Lunes a las 08:00: enlace por cada práctica con días registrados la semana anterior y sin confirmar
    // Lunes a las 08:00: aviso por cada practica con dias sin validar en la semana indicada
    public function enviarEnlaces(CarbonInterface $lunes): int
    {
        $lunes = Carbon::parse($lunes)->startOfWeek();
        [$desde, $hasta] = $this->rango($lunes);

        $practicas = Practica::formalizadas()
            // Solo si su cuenta está abierta: es con ella que confirma
            ->whereHas('supervisor.usuario', fn ($q) => $q->whereNull('fecha_baja'))
            ->whereExists(fn (Builder $q) => $q->from('registros_diarios')
                ->whereColumn('registros_diarios.practica_id', 'practicas.id')
                ->whereBetween('registros_diarios.fecha', [$desde, $hasta]))
            ->get();

        foreach ($practicas as $practica) {
            Mail::to($practica->supervisor->usuario)->queue(new ConfirmacionSemanalMail(
                supervisor: $practica->supervisor->nombre,
                estudiante: $practica->habilitacion->estudiante->usuario->name,
                semana: 'del '.$lunes->format('d/m').' al '.$lunes->copy()->endOfWeek()->format('d/m/Y'),
                enlace: rtrim(config('app.frontend_url'), '/')."/supervisor/asistencia/{$practica->id}/{$lunes->toDateString()}",
            ));
        }

        return $practicas->count();
    }

    // Semanas con dias registrados de sus practicas; primero las que tienen dias por confirmar
    public function delSupervisor(User $cuenta): Collection
    {
            ->with(['supervisor.centro', 'habilitacion.estudiante.usuario'])
            ->get()->keyBy('id');

        $confirmaciones = ConfirmacionSemanal::whereIn('practica_id', $practicas->keys())->get()
            ->keyBy(fn (ConfirmacionSemanal $c) => $c->practica_id.'|'.$c->semana->toDateString());

        return RegistroDiario::whereIn('practica_id', $practicas->keys())->get()
            ->groupBy(fn (RegistroDiario $r) => $r->practica_id.'|'.$r->fecha->copy()->startOfWeek()->toDateString())
            ->map(function (Collection $dias, string $clave) use ($practicas, $confirmaciones) {
                [$practicaId, $semana] = explode('|', $clave);
                $practica = $practicas->get((int) $practicaId);
                $lunes = Carbon::parse($semana);

                return [
                    'practica_id' => $practica->id,
                    'estudiante' => $practica->habilitacion->estudiante->usuario->name,
                    'centro' => $practica->supervisor->centro->nombre,
                    'semana' => ['desde' => $semana, 'hasta' => $lunes->copy()->endOfWeek()->toDateString()],
                    'dias' => $dias->count(),
                    'pendientes' => $dias->whereNull('validacion')->count(),
                    'confirmada_en' => $confirmaciones->get($clave)?->fecha_hora_envio->toIso8601String(),
                ];
            })
            ->sortBy([
                fn ($a, $b) => ($a['pendientes'] === 0) <=> ($b['pendientes'] === 0),
                fn ($a, $b) => strcmp($b['semana']['desde'], $a['semana']['desde']),
            ])->values();
    }

    public function paraSupervisor(User $cuenta, Practica $practica, string $semana): array
    {
        $lunes = $this->deSuPractica($cuenta, $practica, $semana);

        return $this->detalle($practica, $lunes);
    }

    public function confirmar(User $cuenta, Practica $practica, string $semana, array $datos): array
    {
        $lunes = $this->deSuPractica($cuenta, $practica, $semana);

        DB::transaction(function () use ($practica, $lunes, $datos) {
            // Bloquea la practicas para que dos envíos simultaneos no se crucen
            Practica::whereKey($practica->id)->lockForUpdate()->first();

            // Solo se deciden los dias pendientes: los nuevos o los que el estudiante corrigió tras un rechazo
            $pendientes = $this->registros($practica, $lunes)->whereNull('validacion')
                ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            abort_if($pendientes->isEmpty(), 409, 'Esta semana no tiene dias por confirmar.');

                throw ValidationException::withMessages(['dias' => 'Confirma o rechaza cada dia pendiente de la semana.']);
            }

            foreach ($decisiones as $id => $validacion) {
                RegistroDiario::whereKey($id)->whereNull('validacion')->update(['validacion' => $validacion]);
            }

            $confirmacion = $this->confirmacion($practica, $lunes);
            if ($confirmacion) {
                // Revisión de dias corregidos: se conserva la fecha de la primera confirmación
                $observacion = trim((string) ($datos['observacion'] ?? ''));
                $confirmacion->update(['observacion' => $observacion !== '' ? $observacion : $confirmacion->observacion]);
            } else {
                ConfirmacionSemanal::create([
                    'practica_id' => $practica->id,
                    'semana' => $lunes->toDateString(),
                    'supervisor_id' => $practica->supervisor_id,
                    'fecha_hora_envio' => now(),
                    'observacion' => $datos['observacion'] ?? null,
                ]);
            }
        });

        return $this->detalle($practica, $lunes);
    }

    // 404 si la practica no es de este supervisor o no hay dias registrados esa semana
    private function deSuPractica(User $cuenta, Practica $practica, string $semana): Carbon
    {
        $practica = Practica::with(['supervisor.centro', 'habilitacion.estudiante.user'])->findOrFail($practicaId);
        abort_unless($practica->supervisor?->user_id === $cuenta->id && $practica->estaFormalizada(), 404);

        abort_unless($practica->supervisor_id === $supervisorId, 403, 'Este enlace ya no corresponde al supervisor de la práctica.');
        $lunes = Carbon::parse($semana)->startOfWeek();
        abort_if($this->registros($practica, $lunes)->isEmpty(), 404);

        return [$practica, Carbon::parse($semana)->startOfWeek()];
    }

    private function detalle(Practica $practica, Carbon $lunes): array
    {
        $confirmacion = $this->confirmacion($practica, $lunes);
        $dias = $this->registros($practica, $lunes);

        return [
            'practica_id' => $practica->id,
            'estudiante' => $practica->habilitacion->estudiante->usuario->name,
            'centro' => $practica->supervisor->centro->nombre,
            'semana' => ['desde' => $lunes->toDateString(), 'hasta' => $lunes->copy()->endOfWeek()->toDateString()],
            'confirmada_en' => $confirmacion?->fecha_hora_envio->toIso8601String(),
            'observacion' => $confirmacion?->observacion,
            'pendientes' => $dias->whereNull('validacion')->count(),
            'dias' => $dias->map(fn (RegistroDiario $registro) => [
                'id' => $registro->id,
                'hora_entrada' => $registro->hora_entrada ? substr($registro->hora_entrada, 0, 5) : null,
                'hora_salida' => $registro->hora_salida ? substr($registro->hora_salida, 0, 5) : null,
                'horas' => $this->horas($registro),
                'actividades' => $registro->actividades,
                'justificacion' => $registro->justificacion,
                'validacion' => $registro->validacion,
            ])->values(),
        ];
    }

    private function registros(Practica $practica, Carbon $lunes): Collection
    {
        app(AlertaService::class)->resolver($practica->id);
            ->where('practica_id', $practica->id)
        return RegistroDiario::where('practica_id', $practica->id)
            ->whereBetween('fecha', $this->rango($lunes))
            ->orderBy('fecha')
            ->get();
    }

    private function confirmacion(Practica $practica, Carbon $lunes): ?ConfirmacionSemanal
    {
        return ConfirmacionSemanal::where('practica_id', $practica->id)->whereDate('semana', $lunes)->first();
    }

    // Mismo cálculo que la bitácora (HU-13): nunca negativo
    private function horas(RegistroDiario $registro): float
    {
        if (! $registro->hora_entrada || ! $registro->hora_salida) {
            return 0.0;
        }

        $minutos = Carbon::parse($registro->hora_entrada)->diffInMinutes(Carbon::parse($registro->hora_salida), false);

        return round(max(0, $minutos) / 60, 2);
    }

    private function rango(CarbonInterface $lunes): array
    {
        return [$lunes->toDateString(), $lunes->copy()->endOfWeek()->toDateString()];
    }
}