<?php

namespace App\Services;

use App\Mail\PracticaSolicitudNotificacion;
use App\Models\Practica;
use App\Models\RegistroDiario;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class BitacoraService
{
    private const ESTADOS_CONSULTABLES = ['confirmada', 'en_curso', 'finalizada'];
    private const ESTADOS_EDITABLES = ['confirmada', 'en_curso'];

    public function listar(User $usuario): Collection
    {
        $estudiante = $usuario->estudiante;
        if (! $estudiante) {
            return collect();
        }

        return Practica::query()
            ->conEstado(self::ESTADOS_CONSULTABLES)
            ->whereHas('habilitacion', fn ($q) => $q->where('estudiante_id', $estudiante->id))
            ->with(['habilitacion.tipoPractica', 'supervisor.centro', 'registrosDiarios'])
            ->orderByDesc('fecha_inicio')->orderByDesc('id')
            ->get()
            ->map(fn (Practica $practica) => $this->serializarPractica($practica));
    }

    public function detalle(User $usuario, int $practicaId): array
    {
        $practica = $this->buscarPropia($usuario, $practicaId);
        $practica->loadMissing(['habilitacion.tipoPractica', 'supervisor.centro', 'registrosDiarios']);

        return $this->serializarPractica($practica, incluirRegistros: true);
    }

    public function guardarJornada(User $usuario, int $practicaId, string $fecha, array $datos): array
    {
        $registro = DB::transaction(function () use ($usuario, $practicaId, $fecha, $datos) {
            $practica = $this->buscarPropia($usuario, $practicaId, bloquear: true);
            $this->validarPeriodoPractica($practica, $fecha);
            $this->validarJornadaProgramada($practica, $fecha);

            $existente = RegistroDiario::query()
                ->where('practica_id', $practica->id)->whereDate('fecha', $fecha)
                ->lockForUpdate()->first();
            $this->validarPracticaEditable($practica, $existente);
            $this->validarEditable($existente);

            $registro = $existente ?? new RegistroDiario(['practica_id' => $practica->id, 'fecha' => $fecha]);
            $registro->fill([
                'hora_entrada' => $datos['hora_entrada'],
                'hora_salida' => $datos['hora_salida'],
                'actividades' => trim($datos['actividades']),
                // null = pendiente de revisión por el supervisor en HU-26.
                'validacion' => null,
                'justificacion' => null,
                'fecha_justificacion' => null,
                'recuperacion_programada' => null,
            ]);
            $registro->save();

            return $registro->fresh();
        });
        // HU-14: la jornada marcada cierra las alertas que ya no corresponden
        app(AlertaService::class)->resolver($registro->practica_id);

        return $this->serializarRegistro($registro);
    }

    public function informarInasistencia(User $usuario, int $practicaId, string $fecha, array $datos): array
    {
        [$registro, $debeNotificar] = DB::transaction(function () use ($usuario, $practicaId, $fecha, $datos) {
            $practica = $this->buscarPropia($usuario, $practicaId, bloquear: true);
            $this->validarPeriodoPractica($practica, $fecha);
            $horario = $this->horarioParaFecha($practica, $fecha);
            if (! $horario) {
                throw ValidationException::withMessages([
                    'fecha' => 'No hay horario registrado para esta jornada; contacta a Coordinación.',
                ]);
            }

            $existente = RegistroDiario::query()
                ->where('practica_id', $practica->id)->whereDate('fecha', $fecha)
                ->lockForUpdate()->first();
            $this->validarPracticaEditable($practica, $existente);
            $this->validarEditable($existente);

            $inicioProgramado = $this->fechaHora($fecha, $horario->hora_inicio);
            if (now()->greaterThan($inicioProgramado->addDay()) && $existente?->validacion !== 'rechazado') {
                throw ValidationException::withMessages([
                    'justificacion' => 'El aviso de inasistencia debe enviarse dentro de las 24 horas desde el inicio programado de la jornada.',
                ]);
            }

            $recuperacion = $datos['recuperacion_programada'];
            $this->validarPeriodoPractica($practica, $recuperacion, permitirFuturo: true);
            if ($recuperacion <= $fecha) {
                throw ValidationException::withMessages([
                    'recuperacion_programada' => 'La recuperación debe programarse para una fecha posterior a la inasistencia.',
                ]);
            }

            $nuevaNotificacion = ! $existente || ! $existente->justificacion || $existente->validacion === 'rechazado';

            $registro = $existente ?? new RegistroDiario(['practica_id' => $practica->id, 'fecha' => $fecha]);
            $registro->fill([
                'hora_entrada' => null,
                'hora_salida' => null,
                'actividades' => null,
                'validacion' => null,
                'justificacion' => trim($datos['justificacion']),
                // Se conserva el primer aviso cuando el supervisor rechaza y el estudiante corrige.
                'fecha_justificacion' => $registro->fecha_justificacion ?? now(),
                'recuperacion_programada' => $recuperacion,
            ]);
            $registro->save();

            return [$registro->fresh(), $nuevaNotificacion];
        });

        if ($debeNotificar) {
            $this->notificarInasistencia($usuario, $registro);
        }
        app(AlertaService::class)->resolver($registro->practica_id);

        return $this->serializarRegistro($registro);
    }

    private function buscarPropia(User $usuario, int $practicaId, bool $bloquear = false): Practica
    {
        $estudianteId = $usuario->estudiante?->id;
        abort_unless($estudianteId, 404);

        $consulta = Practica::query()
            ->conEstado(self::ESTADOS_CONSULTABLES)
            ->whereKey($practicaId)
            ->whereHas('habilitacion', fn ($q) => $q->where('estudiante_id', $estudianteId));

        if ($bloquear) {
            $consulta->lockForUpdate();
        }

        return $consulta->firstOrFail();
    }

    private function validarPeriodoPractica(Practica $practica, string $fecha, bool $permitirFuturo = false): void
    {
        if (! $practica->fecha_inicio || ! $practica->fecha_fin
            || $fecha < $practica->fecha_inicio->toDateString()
            || $fecha > $practica->fecha_fin->toDateString()) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha debe estar dentro del período registrado para la práctica.',
            ]);
        }

        if (! $permitirFuturo && $fecha > today()->toDateString()) {
            throw ValidationException::withMessages(['fecha' => 'No puedes registrar una jornada futura.']);
        }
    }

    private function validarJornadaProgramada(Practica $practica, string $fecha): void
    {
        if ($this->horarioParaFecha($practica, $fecha)) {
            return;
        }

        $esRecuperacion = RegistroDiario::query()
            ->where('practica_id', $practica->id)
            ->whereDate('recuperacion_programada', $fecha)
            ->exists();

        if (! $esRecuperacion) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha no corresponde a una jornada del horario. Para recuperar horas, debe estar programada en una inasistencia justificada.',
            ]);
        }
    }

    private function horarioParaFecha(Practica $practica, string $fecha): ?object
    {
        $lunes = CarbonImmutable::parse($fecha)->dayOfWeekIso;
        $vigenteDesde = DB::table('horarios')->where('practica_id', $practica->id)
            ->where('vigente_desde', '<=', $fecha)->max('vigente_desde');

        if (! $vigenteDesde) {
            return null;
        }

        return DB::table('horarios')->where('practica_id', $practica->id)
            ->whereDate('vigente_desde', $vigenteDesde)->where('dia_semana', $lunes)->first();
    }

    private function validarEditable(?RegistroDiario $registro): void
    {
        if ($registro && $registro->validacion === 'confirmado') {
            throw ValidationException::withMessages([
                'registro' => 'Esta jornada ya fue confirmada y no se puede modificar.',
            ]);
        }
    }

    private function validarPracticaEditable(Practica $practica, ?RegistroDiario $registro): void
    {
        $estado = $practica->historiales()->value('estado_nuevo');
        $puedeCorregirRechazo = $registro?->validacion === 'rechazado';

        if (! in_array($estado, self::ESTADOS_EDITABLES, true) && ! $puedeCorregirRechazo) {
            throw ValidationException::withMessages([
                'practica' => 'Solo puedes registrar jornadas mientras la práctica está confirmada o en curso.',
            ]);
        }
    }

    private function fechaHora(string $fecha, string $hora): CarbonImmutable
    {
        return CarbonImmutable::parse($fecha.' '.substr((string) $hora, 0, 8), config('app.timezone'));
    }

    private function notificarInasistencia(User $estudiante, RegistroDiario $registro): void
    {
        $registro->loadMissing(['practica.supervisor']);
        $correos = collect([
            $registro->practica->supervisor?->correo,
            ...User::query()->where('rol', 'coordinadora')->whereNull('fecha_baja')->pluck('email')->all(),
        ])->filter()->unique()->values()->all();

        if ($correos === []) {
            Log::warning('No se notificó una inasistencia: no hay destinatarios configurados.', ['registro_diario_id' => $registro->id]);
            return;
        }

        $mensaje = sprintf(
            '%s informó una inasistencia para el %s. Recuperación programada: %s. Justificación: %s',
            $estudiante->name,
            $registro->fecha->format('d-m-Y'),
            $registro->recuperacion_programada?->format('d-m-Y') ?? 'pendiente',
            $registro->justificacion,
        );

        try {
            Mail::to($correos)->queue(new PracticaSolicitudNotificacion('Aviso de inasistencia de práctica', $mensaje));
        } catch (Throwable $error) {
            // El aviso queda guardado aunque el servicio de correo esté temporalmente caído.
            Log::error('No se pudo encolar la notificación de inasistencia.', [
                'registro_diario_id' => $registro->id,
                'error' => $error->getMessage(),
            ]);
        }
    }

    private function serializarPractica(Practica $practica, bool $incluirRegistros = false): array
    {
        $registros = $practica->registrosDiarios->sortByDesc(fn (RegistroDiario $registro) => $registro->fecha->toDateString())->values();
        $minutos = $registros->where('validacion', 'confirmado')->sum(fn (RegistroDiario $registro) => $this->minutos($registro));
        $requeridas = (int) ($practica->habilitacion->horas_requeridas ?? 0);

        $datos = [
            'id' => $practica->id,
            'tipo_practica' => $practica->habilitacion->tipoPractica?->nombre,
            'centro' => $practica->supervisor?->centro?->nombre,
            'estado' => $practica->historiales()->value('estado_nuevo'),
            'fecha_inicio' => $practica->fecha_inicio?->toDateString(),
            'fecha_fin' => $practica->fecha_fin?->toDateString(),
            'horas_requeridas' => $requeridas,
            'minutos_confirmados' => $minutos,
            'horas_confirmadas' => round($minutos / 60, 2),
            'porcentaje_avance' => $requeridas > 0 ? min(100, round($minutos / ($requeridas * 60) * 100, 1)) : 0,
            'puede_registrar' => in_array($practica->historiales()->value('estado_nuevo'), self::ESTADOS_EDITABLES, true)
                && $practica->fecha_inicio !== null && $practica->fecha_fin !== null
                && today()->greaterThanOrEqualTo($practica->fecha_inicio)
                && today()->lessThanOrEqualTo($practica->fecha_fin),
        ];

        if ($incluirRegistros) {
            $datos['registros'] = $registros->map(fn (RegistroDiario $registro) => $this->serializarRegistro($registro))->all();
            $datos['horario'] = $practica->horarios()->orderByDesc('vigente_desde')->get()
                ->groupBy(fn ($horario) => $horario->vigente_desde->toDateString())
                ->map(fn ($horarios, $desde) => [
                    'vigente_desde' => $desde,
                    'dias' => $horarios->sortBy('dia_semana')->map(fn ($horario) => [
                        'dia_semana' => (int) $horario->dia_semana,
                        'hora_inicio' => substr((string) $horario->hora_inicio, 0, 5),
                        'hora_fin' => substr((string) $horario->hora_fin, 0, 5),
                    ])->values()->all(),
                ])->values()->all();
        }

        return $datos;
    }

    private function serializarRegistro(RegistroDiario $registro): array
    {
        return [
            'id' => $registro->id,
            'fecha' => $registro->fecha->toDateString(),
            'hora_entrada' => $registro->hora_entrada ? substr((string) $registro->hora_entrada, 0, 5) : null,
            'hora_salida' => $registro->hora_salida ? substr((string) $registro->hora_salida, 0, 5) : null,
            'actividades' => $registro->actividades,
            'validacion' => $registro->validacion ?? 'pendiente',
            'justificacion' => $registro->justificacion,
            'fecha_justificacion' => $registro->fecha_justificacion?->toIso8601String(),
            'recuperacion_programada' => $registro->recuperacion_programada?->toDateString(),
            'es_inasistencia' => $registro->justificacion !== null,
            'es_recuperacion' => DB::table('registros_diarios')
                ->where('practica_id', $registro->practica_id)
                ->whereDate('recuperacion_programada', $registro->fecha->toDateString())
                ->exists(),
            'horas' => round($this->minutos($registro) / 60, 2),
            'editable' => $registro->validacion !== 'confirmado',
        ];
    }

    private function minutos(RegistroDiario $registro): int
    {
        if (! $registro->hora_entrada || ! $registro->hora_salida) {
            return 0;
        }

        $entrada = CarbonImmutable::parse('2000-01-01 '.substr((string) $registro->hora_entrada, 0, 8));
        $salida = CarbonImmutable::parse('2000-01-01 '.substr((string) $registro->hora_salida, 0, 8));

        return max(0, (int) $entrada->diffInMinutes($salida, false));
    }
}
