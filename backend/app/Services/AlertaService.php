<?php

namespace App\Services;

use App\Models\Alerta;
use App\Models\Practica;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;


class AlertaService
{
    private const ORDEN = ['abandono' => 0, 'inasistencia' => 1, 'sin_confirmar' => 2];

    // Revisa las prácticas en curso y devuelve cuántas alertas nuevas creó
    public function generar(): int
    {
        $hoy = today();

        return $this->enCurso($hoy)->sum(fn (Practica $practica) => $this->revisar($practica, $hoy));
    }

    public function listar(bool $atendidas): Collection
    {
        $alertas = Alerta::with([
            'practica.habilitacion.estudiante.usuario', 'practica.habilitacion.tipoPractica', 'practica.supervisor.centro', 'coordinadora',
        ])
            ->when($atendidas, fn ($q) => $q->whereNotNull('fecha_atencion'), fn ($q) => $q->whereNull('fecha_atencion'))
            ->get();

        // Abiertas: primero las más graves; atendidas: las últimas atendidas primero
        return $atendidas
            ? $alertas->sortByDesc('fecha_atencion')->values()
            : $alertas->sortBy([
                fn (Alerta $a, Alerta $b) => self::ORDEN[$a->tipo] <=> self::ORDEN[$b->tipo],
                fn (Alerta $a, Alerta $b) => $b->fecha_generada <=> $a->fecha_generada,
            ])->values();
    }

    public function contar(): array
    {
        return [
            'abiertas' => Alerta::whereNull('fecha_atencion')->count(),
            'atendidas' => Alerta::whereNotNull('fecha_atencion')->count(),
        ];
    }

    public function atender(Alerta $alerta, User $coordinadora): Alerta
    {
        if ($alerta->fecha_atencion !== null) {
            throw ValidationException::withMessages(['alerta' => 'Esta alerta ya fue atendida.']);
        }

        $alerta->update(['coordinadora_id' => $coordinadora->id, 'fecha_atencion' => now()]);

        return $alerta;
    }

    // Confirmadas o en curso que ya empezaron. Se siguen revisando dos semanas después del término
    // para que alcancen a vencer los plazos de los últimos días.
    private function enCurso(Carbon $hoy): Collection
    {
        return Practica::conEstado(['confirmada', 'en_curso'])
            ->with('horarios')
            ->whereNotNull('fecha_inicio')
            ->whereDate('fecha_inicio', '<', $hoy)
            ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $hoy->copy()->subDays(14)))
            ->get();
    }

    private function revisar(Practica $practica, Carbon $hoy): int
    {
        $registros = DB::table('registros_diarios')->where('practica_id', $practica->id)->get()
            ->keyBy(fn ($registro) => Carbon::parse($registro->fecha)->toDateString());
        $conMarca = function (string $dia) use ($registros): bool {
            $registro = $registros->get($dia);

            return $registro !== null && $registro->hora_entrada !== null && $registro->validacion !== 'rechazado';
        };
        $justificado = fn (string $dia): bool => $registros->get($dia)?->justificacion !== null;
        $nuevas = 0;

        // Roja: racha sin marca que termina en el último día esperado (ayer o antes); la referencia es su primer día
        $racha = $this->diasEsperados($practica, $hoy->copy()->subDay())->reverse()->takeWhile(fn ($dia) => ! $conMarca($dia));
        if ($racha->count() >= 3) {
            $nuevas += $this->registrar($practica, 'abandono', $racha->last());
        }

        // Inasistencia: el día sin marca ni justificación más reciente cuyo plazo de 24 h ya terminó
        $falta = $this->diasEsperados($practica, $hoy->copy()->subDays(2))
            ->reject(fn ($dia) => $conMarca($dia) || $justificado($dia))
            ->last();
        if ($falta !== null) {
            $nuevas += $this->registrar($practica, 'inasistencia', $falta);
        }

        // Amarilla: la semana más reciente que terminó hace más de 7 días sin confirmación del supervisor
        $confirmadas = DB::table('confirmaciones_semanales')->where('practica_id', $practica->id)->pluck('semana')
            ->map(fn ($semana) => Carbon::parse($semana)->toDateString());
        $semana = $this->diasEsperados($practica, $hoy->copy()->subDays(14))
            ->map(fn ($dia) => Carbon::parse($dia)->startOfWeek()->toDateString())
            ->unique()
            ->diff($confirmadas)
            ->last();
        if ($semana !== null) {
            $nuevas += $this->registrar($practica, 'sin_confirmar', $semana);
        }

        return $nuevas;
    }

    // Días (Y-m-d) en que el horario vigente ese día esperaba marca, desde el inicio hasta $hasta o el término
    private function diasEsperados(Practica $practica, Carbon $hasta): Collection
    {
        $fin = $practica->fecha_fin !== null && $practica->fecha_fin->lt($hasta) ? $practica->fecha_fin : $hasta;
        // Cada cambio de horario guarda una versión completa desde su fecha
        $versiones = $practica->horarios->groupBy(fn ($horario) => $horario->vigente_desde->toDateString())->sortKeys();
        $dias = collect();

        foreach (CarbonPeriod::create($practica->fecha_inicio, $fin) as $dia) {
            $fecha = $dia->toDateString();
            $vigente = $versiones->filter(fn ($version, $desde) => $desde <= $fecha)->last();
            if ($vigente?->contains('dia_semana', $dia->dayOfWeekIso)) {
                $dias->push($fecha);
            }
        }

        return $dias;
    }

    // Una alerta abierta por tipo y práctica; tampoco se repite una ya atendida por la misma fecha o una posterior
    private function registrar(Practica $practica, string $tipo, string $referencia): int
    {
        $existe = Alerta::where('practica_id', $practica->id)
            ->where('tipo', $tipo)
            ->where(fn ($q) => $q->whereNull('fecha_atencion')->orWhereDate('fecha_referencia', '>=', $referencia))
            ->exists();
        if ($existe) {
            return 0;
        }

        Alerta::create([
            'practica_id' => $practica->id,
            'tipo' => $tipo,
            'fecha_referencia' => $referencia,
            'fecha_generada' => now(),
        ]);

        return 1;
    }
}
