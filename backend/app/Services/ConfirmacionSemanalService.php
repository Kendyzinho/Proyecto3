<?php

namespace App\Services;

use App\Mail\ConfirmacionSemanalMail;
use App\Models\ConfirmacionSemanal;
use App\Models\Practica;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

// HU-26: el supervisor confirma cada semana, sin cuenta, desde un enlace firmado.
// Lee registros_diarios (HU-13) por tabla, sin depender de su modelo.
class ConfirmacionSemanalService
{
    public const VIGENCIA_DIAS = 30;

    // Lunes a las 08:00: enlace por cada práctica con días registrados la semana anterior y sin confirmar
    public function enviarEnlaces(CarbonInterface $lunes): int
    {
        $lunes = Carbon::parse($lunes)->startOfWeek();
        [$desde, $hasta] = $this->rango($lunes);

        $practicas = Practica::formalizadas()
            ->whereHas('supervisor', fn ($q) => $q->whereNotNull('fecha_alta')->whereNull('fecha_baja'))
            ->whereExists(fn (Builder $q) => $q->from('registros_diarios')
                ->whereColumn('registros_diarios.practica_id', 'practicas.id')
                ->whereBetween('registros_diarios.fecha', [$desde, $hasta]))
            ->whereNotExists(fn (Builder $q) => $q->from('confirmaciones_semanales')
                ->whereColumn('confirmaciones_semanales.practica_id', 'practicas.id')
                ->whereDate('confirmaciones_semanales.semana', $desde))
            ->with(['supervisor', 'habilitacion.estudiante.user'])
            ->get();

        foreach ($practicas as $practica) {
            Mail::to($practica->supervisor->correo)->queue(new ConfirmacionSemanalMail(
                supervisor: $practica->supervisor->nombre,
                estudiante: $practica->habilitacion->estudiante->user->name,
                semana: 'del '.$lunes->format('d/m').' al '.$lunes->copy()->endOfWeek()->format('d/m/Y'),
                enlace: $this->enlace($practica, $lunes),
            ));
        }

        return $practicas->count();
    }

    // El correo apunta a la página de Angular; ella reenvía la misma firma a la API
    public function enlace(Practica $practica, CarbonInterface $lunes): string
    {
        $semana = $lunes->toDateString();
        $firmada = URL::temporarySignedRoute('supervision.semana', now()->addDays(self::VIGENCIA_DIAS), [
            'practica' => $practica->id, 'semana' => $semana, 'supervisor' => $practica->supervisor_id,
        ], absolute: false);

        return rtrim(config('app.frontend_url'), '/')
            ."/supervisor/asistencia/{$practica->id}/{$semana}?".parse_url($firmada, PHP_URL_QUERY);
    }

    /** @return array{0: Practica, 1: Carbon} */
    public function delEnlace(int $practicaId, string $semana, int $supervisorId): array
    {
        $practica = Practica::with(['supervisor.centro', 'habilitacion.estudiante.user'])->findOrFail($practicaId);

        // Si la Coordinadora cambió el supervisor, el enlace anterior deja de servir
        abort_unless($practica->supervisor_id === $supervisorId, 403, 'Este enlace ya no corresponde al supervisor de la práctica.');

        return [$practica, Carbon::parse($semana)->startOfWeek()];
    }

    public function detalle(Practica $practica, CarbonInterface $lunes): array
    {
        $confirmacion = $this->confirmacion($practica, $lunes);

        return [
            'estudiante' => $practica->habilitacion->estudiante->user->name,
            'centro' => $practica->supervisor->centro->nombre,
            'supervisor' => $practica->supervisor->nombre,
            'semana' => ['desde' => $lunes->toDateString(), 'hasta' => $lunes->copy()->endOfWeek()->toDateString()],
            'confirmada_en' => $confirmacion?->fecha_hora_envio->toIso8601String(),
            'observacion' => $confirmacion?->observacion,
            'dias' => $this->registros($practica, $lunes)->map(fn (object $registro) => [
                'id' => $registro->id,
                'fecha' => $registro->fecha,
                'hora_entrada' => $registro->hora_entrada ? substr($registro->hora_entrada, 0, 5) : null,
                'hora_salida' => $registro->hora_salida ? substr($registro->hora_salida, 0, 5) : null,
                'horas' => $this->horas($registro),
                'actividades' => $registro->actividades,
                'validacion' => $registro->validacion,
            ])->values(),
        ];
    }

    public function confirmar(Practica $practica, CarbonInterface $lunes, array $datos): ConfirmacionSemanal
    {
        $confirmacion = DB::transaction(function () use ($practica, $lunes, $datos) {
            // Bloquea la práctica para que dos envíos simultáneos no se crucen
            Practica::whereKey($practica->id)->lockForUpdate()->first();

            abort_if($this->confirmacion($practica, $lunes) !== null, 409, 'Esta semana ya fue confirmada y no se puede modificar.');

            $ids = $this->registros($practica, $lunes)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $decisiones = collect($datos['dias'])->mapWithKeys(fn ($dia) => [(int) $dia['id'] => $dia['validacion']]);

            if ($ids->isEmpty() || $decisiones->keys()->sort()->values()->all() !== $ids->all()) {
                throw ValidationException::withMessages(['dias' => 'Confirma o rechaza cada día registrado en la semana.']);
            }

            foreach ($decisiones as $id => $validacion) {
                DB::table('registros_diarios')->where('id', $id)->update(['validacion' => $validacion, 'updated_at' => now()]);
            }

            return ConfirmacionSemanal::create([
                'practica_id' => $practica->id,
                'semana' => $lunes->toDateString(),
                'supervisor_id' => $practica->supervisor_id,
                'fecha_hora_envio' => now(),
                'observacion' => $datos['observacion'] ?? null,
            ]);
        });

        // HU-14: la semana confirmada cierra su alerta amarilla
        app(AlertaService::class)->resolver($practica->id);

        return $confirmacion;
    }

    private function registros(Practica $practica, CarbonInterface $lunes): Collection
    {
        return DB::table('registros_diarios')
            ->where('practica_id', $practica->id)
            ->whereBetween('fecha', $this->rango($lunes))
            ->orderBy('fecha')
            ->get();
    }

    private function confirmacion(Practica $practica, CarbonInterface $lunes): ?ConfirmacionSemanal
    {
        return ConfirmacionSemanal::where('practica_id', $practica->id)->whereDate('semana', $lunes)->first();
    }

    private function horas(object $registro): float
    {
        if (! $registro->hora_entrada || ! $registro->hora_salida) {
            return 0.0;
        }

        return round(Carbon::parse($registro->hora_entrada)->diffInMinutes(Carbon::parse($registro->hora_salida)) / 60, 2);
    }

    private function rango(CarbonInterface $lunes): array
    {
        return [$lunes->toDateString(), $lunes->copy()->endOfWeek()->toDateString()];
    }
}
