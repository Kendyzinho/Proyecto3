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

// HU-14: alertas tempranas sobre las prácticas en curso
class AlertaService
{
    private const ORDEN = ['abandono' => 0, 'inasistencia' => 1, 'sin_confirmar' => 2];

    private const CARGA = ['practica.habilitacion.estudiante.usuario', 'practica.supervisor'];

    // Revisa las prácticas en curso y devuelve cuántas alertas nuevas creó
    public function generar(): int
    {
        $hoy = today();

        return $this->enCurso($hoy)->sum(function (Practica $practica) use ($hoy) {
            $this->resolver($practica->id);

            return $this->revisar($practica, $hoy);
        });
    }

    // Campana de cada rol (ver NotificacionResource para el formato de cada línea):
    // - Coordinadora y Jefe: una línea por práctica con alertas pendientes, con la más grave primero
    // - Estudiante: sus rojas y naranjas sin resolver
    // - Supervisor(a): las amarillas sin resolver de sus prácticas, con el enlace firmado para confirmar
    public function notificaciones(User $usuario): Collection
    {
        if ($usuario->gestionaPracticas()) {
            return $this->ordenar(Alerta::pendientes()->with(self::CARGA)->get())
                ->groupBy('practica_id')
                ->map(fn (Collection $alertas) => ['alerta' => $alertas->first(), 'otras' => $alertas->count() - 1])
                ->values();
        }

        $consulta = match (true) {
            $usuario->esSupervisor() => Alerta::sinResolver()->where('tipo', 'sin_confirmar')
                ->whereHas('practica', fn ($q) => $q->whereIn('supervisor_id', $usuario->supervisores()->select('id'))),
            $usuario->rol === 'secretaria' => null,
            default => Alerta::sinResolver()->whereIn('tipo', ['abandono', 'inasistencia'])
                ->whereHas('practica.habilitacion.estudiante', fn ($q) => $q->where('user_id', $usuario->id)),
        };

        return $consulta === null ? collect() : $this->ordenar($consulta->with(self::CARGA)->get())
            ->map(fn (Alerta $alerta) => ['alerta' => $alerta, 'otras' => 0])
            ->values();
    }

    // Ficha del estudiante (HU-17): pendientes para atender e historial de las cerradas
    public function dePractica(Practica $practica): array
    {
        $alertas = Alerta::where('practica_id', $practica->id)->with('coordinadora')->get();
        [$cerradas, $pendientes] = $alertas->partition(fn (Alerta $alerta) => $alerta->estaCerrada());

        return [
            'pendientes' => $this->ordenar($pendientes)->values(),
            'historial' => $cerradas->sortByDesc(fn (Alerta $a) => max($a->fecha_atencion, $a->fecha_resolucion))->values(),
        ];
    }

    public function atender(Alerta $alerta, User $coordinadora, ?string $nota): Alerta
    {
        if ($alerta->fecha_resolucion !== null) {
            throw ValidationException::withMessages(['alerta' => 'Esta alerta ya se resolvió.']);
        }
        if ($alerta->fecha_atencion !== null) {
            throw ValidationException::withMessages(['alerta' => 'Esta alerta ya fue atendida.']);
        }

        $nota = trim((string) $nota);
        $alerta->update(['coordinadora_id' => $coordinadora->id, 'fecha_atencion' => now(), 'nota_atencion' => $nota === '' ? null : $nota]);

        return $alerta->load('coordinadora');
    }

    // Cierra las alertas de la práctica cuya causa ya desapareció. Se llama al guardar la bitácora (HU-13),
    // al confirmar la semana (HU-26) y en la revisión de cada mañana.
    public function resolver(int $practicaId): int
    {
        $abiertas = Alerta::where('practica_id', $practicaId)->sinResolver()->whereNotNull('fecha_referencia')->get();
        if ($abiertas->isEmpty()) {
            return 0;
        }

        $registros = DB::table('registros_diarios')->where('practica_id', $practicaId)->get();
        $marcado = fn (object $r): bool => $r->hora_entrada !== null && $r->validacion !== 'rechazado';
        $fecha = fn (object $r): string => substr((string) $r->fecha, 0, 10);

        $resueltas = $abiertas->filter(function (Alerta $alerta) use ($registros, $marcado, $fecha, $practicaId) {
            $referencia = $alerta->fecha_referencia->toDateString();

            return match ($alerta->tipo) {
                // Volvió a marcar algún día desde que empezó la racha
                'abandono' => $registros->contains(fn ($r) => $fecha($r) >= $referencia && $marcado($r)),
                // Ese día ya tiene marca o justificación
                'inasistencia' => $registros->contains(fn ($r) => $fecha($r) === $referencia && ($marcado($r) || $r->justificacion !== null)),
                // El supervisor confirmó esa semana, o la semana no tiene días registrados que confirmar
                'sin_confirmar' => DB::table('confirmaciones_semanales')->where('practica_id', $practicaId)
                    ->whereDate('semana', $referencia)->exists()
                    || ! $registros->contains(fn ($r) => $fecha($r) >= $referencia && $fecha($r) <= Carbon::parse($referencia)->endOfWeek()->toDateString()),
                default => false,
            };
        });

        if ($resueltas->isNotEmpty()) {
            Alerta::whereKey($resueltas->modelKeys())->update(['fecha_resolucion' => now()]);
        }

        return $resueltas->count();
    }

    // La más grave primero y, dentro del mismo tipo, la más reciente
    private function ordenar(Collection $alertas): Collection
    {
        return $alertas->sortBy([
            fn (Alerta $a, Alerta $b) => self::ORDEN[$a->tipo] <=> self::ORDEN[$b->tipo],
            fn (Alerta $a, Alerta $b) => $b->fecha_generada <=> $a->fecha_generada,
        ])->values();
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

        $falta = $this->diasEsperados($practica, $hoy->copy()->subDays(2))
            ->reject(fn ($dia) => $conMarca($dia) || $justificado($dia))
            ->last();
        if ($falta !== null) {
            $nuevas += $this->registrar($practica, 'inasistencia', $falta);
        }

        // Amarilla: la semana más reciente que terminó hace más de 7 días sin confirmación del supervisor.
        // Solo cuentan las semanas con días registrados: sin registros no hay nada que confirmar (HU-26)
        $confirmadas = DB::table('confirmaciones_semanales')->where('practica_id', $practica->id)->pluck('semana')
            ->map(fn ($semana) => Carbon::parse($semana)->toDateString());
        $semana = $this->diasEsperados($practica, $hoy->copy()->subDays(14))
            ->filter(fn ($dia) => $registros->has($dia))
            ->map(fn ($dia) => Carbon::parse($dia)->startOfWeek()->toDateString())
            ->unique()
            ->diff($confirmadas)
            ->last();
        if ($semana !== null) {
            $nuevas += $this->registrar($practica, 'sin_confirmar', $semana);
        }

        return $nuevas;
    }

    private function diasEsperados(Practica $practica, Carbon $hasta): Collection
    {
        $fin = $practica->fecha_fin !== null && $practica->fecha_fin->lt($hasta) ? $practica->fecha_fin : $hasta;
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

    // Una alerta abierta por tipo y práctica; tampoco se repite una ya cerrada por la misma fecha o una posterior
    private function registrar(Practica $practica, string $tipo, string $referencia): int
    {
        // Si la misma alerta se había resuelto sola y la causa volvió, se reabre (una atendida no se toca)
        $misma = Alerta::where('practica_id', $practica->id)->where('tipo', $tipo)->whereDate('fecha_referencia', $referencia)->first();
        if ($misma?->fecha_resolucion !== null && $misma->fecha_atencion === null) {
            $misma->update(['fecha_resolucion' => null]);

            return 1;
        }

        $existe = Alerta::where('practica_id', $practica->id)
            ->where('tipo', $tipo)
            ->where(fn ($q) => $q->where(fn ($abierta) => $abierta->whereNull('fecha_atencion')->whereNull('fecha_resolucion'))
                ->orWhereDate('fecha_referencia', '>=', $referencia))
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
