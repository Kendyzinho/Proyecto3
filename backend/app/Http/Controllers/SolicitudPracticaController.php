<?php

namespace App\Http\Controllers;

use App\Mail\PracticaSolicitudNotificacion;
use App\Models\CentroPractica;
use App\Models\HistorialPractica;
use App\Models\Practica;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SolicitudPracticaController extends Controller
{
    private const ESTADOS_CUPO = ['confirmada', 'en_curso', 'finalizada'];

    public function estudiante(Request $request): JsonResponse
    {
        $estudiante = DB::table('estudiantes')->where('user_id', $request->user()->id)->first();
        if (!$estudiante) {
            return response()->json(['data' => [
                'habilitacion' => ['id' => null, 'puede_postular' => false, 'motivo' => 'Tu cuenta todavía no está asociada a una habilitación de práctica profesional.'],
                'solicitud' => null,
            ]]);
        }

        $habilitacion = DB::table('habilitaciones')
            ->join('tipos_practica', 'tipos_practica.id', '=', 'habilitaciones.tipo_practica_id')
            ->join('periodos', 'periodos.id', '=', 'habilitaciones.periodo_id')
            ->where('habilitaciones.estudiante_id', $estudiante->id)
            ->where('tipos_practica.categoria', 'profesional')
            ->whereNull('tipos_practica.fecha_baja')
            ->orderByDesc('periodos.anio')->orderByDesc('periodos.semestre')
            ->select('habilitaciones.*', 'periodos.anio', 'periodos.semestre', 'tipos_practica.nombre as tipo_nombre')
            ->first();

        if (!$habilitacion) {
            return response()->json(['data' => [
                'habilitacion' => ['id' => null, 'puede_postular' => false, 'motivo' => 'No tienes una habilitación vigente para la Práctica Profesional.'],
                'solicitud' => null,
            ]]);
        }

        $practica = Practica::query()->where('habilitacion_id', $habilitacion->id)
            ->with(['supervisor.centro', 'habilitacion.estudiante.user', 'historiales', 'horarios'])
            ->orderByDesc('id')->get()
            ->first(fn (Practica $item) => $this->estadoActual($item) !== 'cancelada');

        $estado = $practica ? $this->estadoActual($practica) : null;
        $puedePostular = (bool) $habilitacion->cumple_prerrequisito && !$practica;
        $motivo = !$habilitacion->cumple_prerrequisito
            ? 'La Secretaría aún no ha registrado como cumplido el prerrequisito de tu habilitación.'
            : ($practica ? 'Ya existe una práctica vinculada a esta habilitación. Revisa su estado antes de crear otra.' : '');

        return response()->json(['data' => [
            'habilitacion' => [
                'id' => $habilitacion->id,
                'puede_postular' => $puedePostular || in_array($estado, ['borrador', 'devuelta'], true),
                'motivo' => $motivo,
                'periodo_id' => $habilitacion->periodo_id,
                'periodo' => "{$habilitacion->semestre}° sem {$habilitacion->anio}",
                'cumple_prerrequisito' => (bool) $habilitacion->cumple_prerrequisito,
            ],
            'solicitud' => $practica && $practica->origen === 'solicitud' ? $this->serializar($practica) : null,
            'borrador' => $practica ? null : $this->leerBorrador($habilitacion->id, $request->user()->id),
        ]]);
    }

    public function bandeja(Request $request): JsonResponse
    {
        $this->requireCoordinator($request);
        $solicitudes = Practica::query()->where('origen', 'solicitud')
            ->with(['supervisor.centro', 'habilitacion.estudiante.user', 'habilitacion.periodo', 'historiales', 'horarios'])
            ->orderByDesc('updated_at')->get()
            ->filter(fn (Practica $practica) => in_array($this->estadoActual($practica), ['por_revisar', 'devuelta', 'confirmada'], true))
            ->map(fn (Practica $practica) => $this->serializar($practica))->values();

        return response()->json(['data' => $solicitudes]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->requireStudent($request);
        if ($request->input('accion') === 'borrador') {
            return $this->guardarBorrador($request);
        }
        $datos = $this->validarSolicitud($request);
        $accion = $datos['accion'];
        $estudiante = DB::table('estudiantes')->where('user_id', $request->user()->id)->first();
        if (!$estudiante) {
            throw ValidationException::withMessages(['habilitacion' => 'Tu cuenta no tiene una ficha de estudiante registrada.']);
        }

        $habilitacion = DB::table('habilitaciones')
            ->join('tipos_practica', 'tipos_practica.id', '=', 'habilitaciones.tipo_practica_id')
            ->join('periodos', 'periodos.id', '=', 'habilitaciones.periodo_id')
            ->where('habilitaciones.estudiante_id', $estudiante->id)
            ->where('tipos_practica.categoria', 'profesional')
            ->whereNull('tipos_practica.fecha_baja')
            ->orderByDesc('periodos.anio')->orderByDesc('periodos.semestre')
            ->select('habilitaciones.*')->first();
        if (!$habilitacion || !$habilitacion->cumple_prerrequisito) {
            throw ValidationException::withMessages(['habilitacion' => 'No puedes postular porque tu habilitación profesional no cumple el prerrequisito.']);
        }

        $resultado = DB::transaction(function () use ($datos, $habilitacion, $request, $accion) {
            DB::table('habilitaciones')->where('id', $habilitacion->id)->lockForUpdate()->first();
            $candidatas = Practica::query()->where('habilitacion_id', $habilitacion->id)
                ->with('historiales')->lockForUpdate()->orderByDesc('id')->get();
            $actual = $candidatas->first(fn (Practica $item) => $this->estadoActual($item) !== 'cancelada');
            $estadoActual = $actual ? $this->estadoActual($actual) : null;

            if ($actual && !in_array($estadoActual, ['borrador', 'devuelta'], true)) {
                throw ValidationException::withMessages(['solicitud' => 'Esta habilitación ya tiene una práctica activa y no admite otra solicitud.']);
            }

            $centro = $this->resolverCentro($datos);
            $supervisor = $this->resolverSupervisor($datos, $centro);
            $this->validarHorario($datos);
            $nuevoEstado = $accion === 'enviar' ? 'por_revisar' : 'borrador';

            $atributos = [
                'habilitacion_id' => $habilitacion->id,
                'supervisor_id' => $supervisor?->id,
                'origen' => 'solicitud',
                // La propuesta puede haber sido formalizada desde HU-12 mientras se revisaba la solicitud.
                'centro_propuesto_en_solicitud' => !$centro->fecha_alta || $centro->fecha_alta->isFuture(),
                'supervisor_propuesto_en_solicitud' => !$supervisor?->fecha_alta || $supervisor->fecha_alta->isFuture(),
                'modalidad' => $this->modalidadDb($datos['modalidad']),
                'tareas' => trim($datos['tareas']),
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_termino'],
            ];

            if ($actual) {
                $actual->update($atributos);
                $practica = $actual;
            } else {
                $practica = Practica::create($atributos);
            }

            if ($supervisor) {
                $this->guardarHorarios($practica, $datos['horario'], $datos['fecha_inicio']);
            }

            if (!$actual || $estadoActual !== $nuevoEstado) {
                HistorialPractica::create([
                    'practica_id' => $practica->id,
                    'user_id' => $request->user()->id,
                    'fecha_hora' => now(),
                    'estado_nuevo' => $nuevoEstado,
                    'motivo' => null,
                ]);
            }

            DB::table('solicitud_practica_borradores')->where('habilitacion_id', $habilitacion->id)->delete();

            return [
                'practica' => $practica->fresh(['supervisor.centro', 'habilitacion.estudiante.user', 'habilitacion.periodo', 'historiales', 'horarios']),
                'creada' => !$actual,
            ];
        });
        $practica = $resultado['practica'];

        if ($accion === 'enviar') {
            $this->notificarCoordinacion($practica);
        }

        return response()->json(['data' => $this->serializar($practica)], $accion === 'enviar' && $resultado['creada'] ? 201 : 200);
    }

    public function decidir(Request $request, Practica $practica): JsonResponse
    {
        $this->requireCoordinator($request);
        abort_unless($practica->origen === 'solicitud', 404);
        $datos = $request->validate([
            'decision' => ['required', Rule::in(['aprobar', 'devolver'])],
            'motivo' => ['required_if:decision,devolver', 'nullable', 'string', 'max:2000'],
        ]);

        $actualizado = DB::transaction(function () use ($practica, $datos, $request) {
            $locked = Practica::query()->whereKey($practica->id)->with(['supervisor.centro', 'habilitacion.periodo', 'historiales'])->lockForUpdate()->firstOrFail();
            if ($this->estadoActual($locked) !== 'por_revisar') {
                throw ValidationException::withMessages(['decision' => 'Solo se pueden decidir solicitudes que están por revisar.']);
            }

            if ($datos['decision'] === 'aprobar') {
                $this->validarCentroYSupervisorAprobables($locked);
                $this->reservarCupoProfesional($locked);
                $nuevoEstado = 'confirmada';
                $motivo = null;
            } else {
                $nuevoEstado = 'devuelta';
                $motivo = trim((string) $datos['motivo']);
            }

            HistorialPractica::create([
                'practica_id' => $locked->id,
                'user_id' => $request->user()->id,
                'fecha_hora' => now(),
                'estado_nuevo' => $nuevoEstado,
                'motivo' => $motivo,
            ]);

            return $locked->fresh(['supervisor.centro', 'habilitacion.estudiante.user', 'habilitacion.periodo', 'historiales', 'horarios']);
        });

        $this->notificarEstudiante($actualizado, $datos['decision'], $datos['motivo'] ?? null);

        return response()->json(['data' => $this->serializar($actualizado)]);
    }

    private function guardarBorrador(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'datos' => ['required', 'array'],
            'datos.modoCentro' => ['nullable', Rule::in(['Registrado', 'Propuesto'])],
            'datos.centroId' => ['nullable', 'integer'],
            'datos.centroRazonSocial' => ['nullable', 'string', 'max:255'],
            'datos.centroRut' => ['nullable', 'string', 'max:12'],
            'datos.modoSupervisor' => ['nullable', Rule::in(['Registrado', 'Propuesto'])],
            'datos.supervisorId' => ['nullable', 'string', 'max:255'],
            'datos.supervisorNombre' => ['nullable', 'string', 'max:255'],
            'datos.supervisorCorreo' => ['nullable', 'string', 'max:255'],
            'datos.supervisorCargo' => ['nullable', 'string', 'max:255'],
            'datos.supervisorTelefono' => ['nullable', 'string', 'max:30'],
            'datos.supervisorUnidad' => ['nullable', 'string', 'max:255'],
            'datos.tareas' => ['nullable', 'string', 'max:10000'],
            'datos.horario' => ['nullable', 'array'],
            'datos.horario.modalidad' => ['nullable', Rule::in(['', 'Presencial', 'A distancia', 'Mixta'])],
            'datos.horario.fechaInicio' => ['nullable', 'date'],
            'datos.horario.fechaTermino' => ['nullable', 'date'],
            'datos.horario.dias' => ['nullable', 'array', 'max:7'],
            'datos.horario.dias.*' => ['string', Rule::in(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'])],
            'datos.horario.horaDesde' => ['nullable', 'date_format:H:i'],
            'datos.horario.horaHasta' => ['nullable', 'date_format:H:i'],
        ]);

        $estudiante = DB::table('estudiantes')->where('user_id', $request->user()->id)->first();
        $habilitacion = $estudiante ? DB::table('habilitaciones')
            ->join('tipos_practica', 'tipos_practica.id', '=', 'habilitaciones.tipo_practica_id')
            ->where('habilitaciones.estudiante_id', $estudiante->id)
            ->where('tipos_practica.categoria', 'profesional')
            ->whereNull('tipos_practica.fecha_baja')
            ->orderByDesc('habilitaciones.id')->select('habilitaciones.*')->first() : null;

        if (!$habilitacion || !$habilitacion->cumple_prerrequisito) {
            throw ValidationException::withMessages(['habilitacion' => 'No puedes guardar un borrador porque tu habilitación profesional no cumple el prerrequisito.']);
        }

        DB::table('solicitud_practica_borradores')->updateOrInsert(
            ['habilitacion_id' => $habilitacion->id],
            [
                'user_id' => $request->user()->id,
                'datos' => json_encode($datos['datos'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return response()->json(['data' => ['guardado' => true]]);
    }

    private function leerBorrador(int $habilitacionId, int $userId): ?array
    {
        $borrador = DB::table('solicitud_practica_borradores')
            ->where('habilitacion_id', $habilitacionId)->where('user_id', $userId)->first();

        if (!$borrador) return null;
        return json_decode($borrador->datos, true) ?: null;
    }

    private function validarSolicitud(Request $request): array
    {
        $request->merge(['centro_rut' => $this->normalizarRut((string) $request->input('centro_rut', ''))]);
        return $request->validate([
            'accion' => ['required', Rule::in(['borrador', 'enviar'])],
            'centro_propuesto' => ['required', 'boolean'],
            'centro_id' => ['required_if:centro_propuesto,false', 'nullable', 'integer', 'exists:centros_practica,id'],
            'centro_razon_social' => ['required', 'string', 'max:255'],
            'centro_rut' => ['required', 'string', 'max:12'],
            'supervisor_propuesto' => ['required', 'boolean'],
            'supervisor_correo' => ['nullable', 'email', 'max:255'],
            'supervisor' => ['required', 'array'],
            'supervisor.nombre' => ['required', 'string', 'max:255'],
            'supervisor.correo' => ['required', 'email', 'max:255'],
            'supervisor.cargo' => ['nullable', 'string', 'max:255'],
            'supervisor.telefono' => ['nullable', 'string', 'max:30'],
            'supervisor.unidad' => ['nullable', 'string', 'max:255'],
            'modalidad' => ['required', Rule::in(['Presencial', 'A distancia', 'Mixta'])],
            'fecha_inicio' => ['required', 'date'],
            'fecha_termino' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'tareas' => ['required', 'string', 'max:10000'],
            'horario.dias' => ['required', 'array', 'min:1'],
            'horario.dias.*' => ['required', Rule::in(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'])],
            'horario.hora_desde' => ['required', 'date_format:H:i'],
            'horario.hora_hasta' => ['required', 'date_format:H:i', 'after:horario.hora_desde'],
        ]);
    }

    private function resolverCentro(array $datos): CentroPractica
    {
        if (!$datos['centro_propuesto']) {
            $centro = CentroPractica::query()->findOrFail($datos['centro_id']);
            if (!$this->centroElegible($centro)) {
                throw ValidationException::withMessages(['centro_id' => 'El centro seleccionado ya no está activo o no tiene un acuerdo vigente.']);
            }
            return $centro;
        }

        if (!empty($datos['centro_id'])) {
            $centro = CentroPractica::query()->findOrFail($datos['centro_id']);
            if ($centro->rut !== $this->formatearRut($datos['centro_rut'])) {
                throw ValidationException::withMessages(['centro_rut' => 'El centro propuesto cambió. Vuelve a revisar sus datos.']);
            }
            if ($centro->fecha_baja && $centro->fecha_baja->lessThanOrEqualTo(today())) {
                throw ValidationException::withMessages(['centro_id' => 'El centro anterior fue dado de baja. Elige o propone otro centro para reenviar.']);
            }
            if ($centro->fecha_alta && !$this->centroElegible($centro)) {
                throw ValidationException::withMessages(['centro_id' => 'El centro ya no es elegible. Elige otro centro o espera a que se regularice su acuerdo.']);
            }
            return $centro;
        }

        $rut = $this->formatearRut($datos['centro_rut']);
        $propuestaExistente = CentroPractica::query()->where('rut', $rut)->first();
        if ($propuestaExistente && !$propuestaExistente->fecha_alta) {
            return $propuestaExistente;
        }
        if ($propuestaExistente) {
            throw ValidationException::withMessages(['centro_rut' => 'Ya existe un centro con ese RUT. Selecciónalo desde el catálogo o contacta a la Coordinadora.']);
        }
        return CentroPractica::create([
            'rut' => $rut,
            'razon_social' => trim($datos['centro_razon_social']),
            'nombre' => trim($datos['centro_razon_social']),
            'es_interno' => false,
            'fecha_alta' => null,
        ]);
    }

    private function resolverSupervisor(array $datos, CentroPractica $centro): ?Supervisor
    {
        if (!$datos['supervisor_propuesto']) {
            $supervisor = Supervisor::query()->where('centro_id', $centro->id)
                ->where('correo', $datos['supervisor_correo'])->whereNotNull('fecha_alta')->whereNull('fecha_baja')->first();
            if (!$supervisor) {
                throw ValidationException::withMessages(['supervisor_correo' => 'Selecciona un supervisor activo asociado a este centro.']);
            }
            return $supervisor;
        }

        $supervisorExistente = $centro->supervisores()->where('correo', trim($datos['supervisor']['correo']))->first();
        if ($supervisorExistente && $supervisorExistente->fecha_baja) {
            throw ValidationException::withMessages(['supervisor.correo' => 'Este supervisor fue dado de baja. Elige un supervisor activo o propone uno nuevo con otro correo.']);
        }
        if ($supervisorExistente && $supervisorExistente->fecha_alta) {
            return $supervisorExistente;
        }
        if ($supervisorExistente && empty($supervisorExistente->fecha_alta)) {
            $supervisorExistente->update([
                'nombre' => trim($datos['supervisor']['nombre']),
                'cargo' => isset($datos['supervisor']['cargo']) ? trim($datos['supervisor']['cargo']) : null,
                'telefono' => isset($datos['supervisor']['telefono']) ? trim($datos['supervisor']['telefono']) : null,
                'unidad' => isset($datos['supervisor']['unidad']) ? trim($datos['supervisor']['unidad']) : null,
            ]);
            return $supervisorExistente;
        }
        if ($supervisorExistente) {
            throw ValidationException::withMessages(['supervisor.correo' => 'Este correo ya está asociado a un supervisor del centro. Selecciónalo como registrado.']);
        }

        return $centro->supervisores()->create([
            'nombre' => trim($datos['supervisor']['nombre']),
            'correo' => trim($datos['supervisor']['correo']),
            'cargo' => isset($datos['supervisor']['cargo']) ? trim($datos['supervisor']['cargo']) : null,
            'telefono' => isset($datos['supervisor']['telefono']) ? trim($datos['supervisor']['telefono']) : null,
            'unidad' => isset($datos['supervisor']['unidad']) ? trim($datos['supervisor']['unidad']) : null,
            'fecha_alta' => null,
            'fecha_baja' => null,
        ]);
    }

    private function validarHorario(array $datos): void
    {
        if ($datos['accion'] === 'enviar' && !$this->rutValido($datos['centro_rut'])) {
            throw ValidationException::withMessages(['centro_rut' => 'Ingresa un RUT chileno válido.']);
        }
    }

    private function guardarHorarios(Practica $practica, array $horario, string $vigenteDesde): void
    {
        $dias = ['Lun' => 1, 'Mar' => 2, 'Mié' => 3, 'Jue' => 4, 'Vie' => 5, 'Sáb' => 6, 'Dom' => 7];
        $practica->horarios()->delete();
        foreach (array_unique($horario['dias']) as $dia) {
            $practica->horarios()->create([
                'dia_semana' => $dias[$dia],
                'hora_inicio' => $horario['hora_desde'],
                'hora_fin' => $horario['hora_hasta'],
                'vigente_desde' => $vigenteDesde,
            ]);
        }
    }

    private function validarCentroYSupervisorAprobables(Practica $practica): void
    {
        $centro = $practica->supervisor?->centro;
        $supervisor = $practica->supervisor;
        if (!$centro || !$this->centroElegible($centro)) {
            throw ValidationException::withMessages(['centro' => 'No se puede confirmar todavía: el centro debe estar activo y tener acuerdo vigente (excepto la UTA).']);
        }
        if (!$supervisor || $supervisor->fecha_baja) {
            throw ValidationException::withMessages(['supervisor' => 'No se puede confirmar todavía: el supervisor debe estar registrado y activo en este centro.']);
        }
        // La Coordinadora debe activar al supervisor desde HU-12 antes de confirmar HU-09.
        if (!$supervisor->fecha_alta || $supervisor->fecha_alta->isFuture()) {
            throw ValidationException::withMessages(['supervisor' => 'No se puede confirmar todavía: el supervisor debe estar activo en este centro.']);
        }
    }

    private function centroElegible(CentroPractica $centro): bool
    {
        if (!$centro->fecha_alta || $centro->fecha_alta->isFuture()
            || ($centro->fecha_baja && $centro->fecha_baja->lessThanOrEqualTo(today()))) return false;
        if ($centro->es_interno) return true;
        return $centro->acuerdos()->whereNotNull('fecha_inicio')->whereDate('fecha_inicio', '<=', today())
            ->whereNotNull('archivo_pdf')
            ->where(fn (Builder $query) => $query->whereNull('fecha_termino')->orWhereDate('fecha_termino', '>=', today()))->exists();
    }

    private function reservarCupoProfesional(Practica $practica): void
    {
        $habilitacion = $practica->habilitacion()->with(['periodo'])->first();
        $centro = $practica->supervisor->centro;
        $requerimiento = $centro->requerimientos()->where('periodo_id', $habilitacion->periodo_id)
            ->where('categoria', 'profesional')->lockForUpdate()->first();

        if (!$requerimiento && $practica->centro_propuesto_en_solicitud) {
            $requerimiento = $centro->requerimientos()->create([
                'periodo_id' => $habilitacion->periodo_id,
                'categoria' => 'profesional',
                'cupos' => 1,
            ]);
        }
        if (!$requerimiento) {
            throw ValidationException::withMessages(['cupo' => 'El centro no tiene cupos profesionales definidos para este período.']);
        }

        $ocupados = DB::table('practicas')->join('supervisores', 'supervisores.id', '=', 'practicas.supervisor_id')
            ->where('supervisores.centro_id', $centro->id)
            ->join('habilitaciones', 'habilitaciones.id', '=', 'practicas.habilitacion_id')
            ->join('tipos_practica', 'tipos_practica.id', '=', 'habilitaciones.tipo_practica_id')
            ->where('habilitaciones.periodo_id', $habilitacion->periodo_id)
            ->where('tipos_practica.categoria', 'profesional')
            ->where('practicas.id', '<>', $practica->id)
            ->whereRaw('(select h.estado_nuevo from historiales_practica h where h.practica_id = practicas.id order by h.fecha_hora desc, h.id desc limit 1) in (?, ?, ?)', self::ESTADOS_CUPO)
            ->count();
        if ($ocupados >= $requerimiento->cupos) {
            throw ValidationException::withMessages(['cupo' => 'El centro ya no tiene cupos profesionales disponibles para este período.']);
        }
    }

    private function serializar(Practica $practica): array
    {
        $practica->loadMissing(['supervisor.centro.acuerdos', 'habilitacion.estudiante.user', 'habilitacion.periodo', 'historiales', 'horarios']);
        $supervisor = $practica->supervisor;
        $centro = $supervisor?->centro;
        $historialActual = $practica->historiales->first();
        $estado = $historialActual?->estado_nuevo ?? 'borrador';
        $estadoUi = match ($estado) {
            'por_revisar' => 'Por revisar', 'devuelta' => 'Devuelta', 'confirmada' => 'Aprobada',
            'en_curso' => 'En curso', 'finalizada' => 'Finalizada', 'cancelada' => 'Cancelada', default => 'Borrador',
        };
        $horario = $practica->horarios->sortBy('dia_semana');
        $diaLabels = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $firstSchedule = $horario->first();

        return [
            'id' => $practica->id,
            'habilitacion_id' => $practica->habilitacion_id,
            'estudianteNombre' => $practica->habilitacion?->estudiante?->user?->name ?? '',
            'estudianteCorreo' => $practica->habilitacion?->estudiante?->user?->email ?? '',
            'estado' => $estadoUi,
            'habilitacionCumplida' => (bool) $practica->habilitacion?->cumple_prerrequisito,
            'modoCentro' => $practica->centro_propuesto_en_solicitud ? 'Propuesto' : 'Registrado',
            'centroId' => $centro?->id,
            'centroRazonSocial' => $centro?->razon_social ?? '',
            'centroRut' => $centro?->rut ?? '',
            'modoSupervisor' => $practica->supervisor_propuesto_en_solicitud ? 'Propuesto' : 'Registrado',
            'supervisorId' => $supervisor?->correo,
            'supervisorNombre' => $supervisor?->nombre ?? '',
            'supervisorCorreo' => $supervisor?->correo ?? '',
            'supervisorCargo' => $supervisor?->cargo ?? '',
            'supervisorTelefono' => $supervisor?->telefono ?? '',
            'supervisorUnidad' => $supervisor?->unidad ?? '',
            'tareas' => $practica->tareas ?? '',
            'horario' => [
                'modalidad' => match ($practica->modalidad) { 'presencial' => 'Presencial', 'a_distancia' => 'A distancia', 'mixta' => 'Mixta', default => '' },
                'fechaInicio' => $practica->fecha_inicio?->toDateString() ?? '',
                'fechaTermino' => $practica->fecha_fin?->toDateString() ?? '',
                'dias' => $horario->pluck('dia_semana')->map(fn ($dia) => $diaLabels[$dia] ?? null)->filter()->values()->all(),
                'horaDesde' => $firstSchedule?->hora_inicio ? substr((string) $firstSchedule->hora_inicio, 0, 5) : '',
                'horaHasta' => $firstSchedule?->hora_fin ? substr((string) $firstSchedule->hora_fin, 0, 5) : '',
            ],
            'motivoDevolucion' => $estado === 'devuelta' ? $historialActual?->motivo : null,
            'actualizadaEn' => $historialActual?->fecha_hora?->toISOString() ?? $practica->updated_at?->toISOString(),
        ];
    }

    private function estadoActual(Practica $practica): string
    {
        return (string) ($practica->historiales->first()?->estado_nuevo ?? 'borrador');
    }

    private function modalidadDb(string $modalidad): string
    {
        return match ($modalidad) { 'Presencial' => 'presencial', 'A distancia' => 'a_distancia', 'Mixta' => 'mixta' };
    }

    private function requireCoordinator(Request $request): void
    {
        abort_unless($request->user()?->rol === 'coordinadora', 403, 'Esta acción es exclusiva de la Coordinadora.');
    }

    private function requireStudent(Request $request): void
    {
        abort_unless($request->user()?->rol === null && DB::table('estudiantes')->where('user_id', $request->user()->id)->exists(), 403, 'Esta acción es exclusiva de un estudiante habilitado.');
    }

    private function notificarCoordinacion(Practica $practica): void
    {
        $destinatarios = DB::table('users')->where('rol', 'coordinadora')->pluck('email');
        foreach ($destinatarios as $email) $this->encolarCorreo($email, 'Nueva solicitud de práctica profesional', 'Se recibió una solicitud de práctica profesional y está disponible para revisión.');
    }

    private function notificarEstudiante(Practica $practica, string $decision, ?string $motivo): void
    {
        $usuario = $practica->habilitacion?->estudiante?->user;
        if (!$usuario?->email) return;
        $titulo = $decision === 'aprobar' ? 'Solicitud de práctica confirmada' : 'Solicitud de práctica devuelta';
        $mensaje = $decision === 'aprobar' ? 'La Coordinadora confirmó tu solicitud.' : 'La Coordinadora devolvió tu solicitud. Motivo: '.($motivo ?? '');
        $this->encolarCorreo($usuario->email, $titulo, $mensaje);
    }

    private function encolarCorreo(string $email, string $asunto, string $mensaje): void
    {
        try {
            Mail::to($email)->queue(new PracticaSolicitudNotificacion($asunto, $mensaje));
        } catch (\Throwable $error) {
            Log::error('No se pudo encolar el correo de HU-09.', ['destinatario' => $email, 'error' => $error->getMessage()]);
        }
    }

    private function normalizarRut(string $rut): string
    {
        $value = strtoupper(preg_replace('/[^0-9K]/', '', $rut) ?? '');
        return strlen($value) > 1 ? substr($value, 0, -1).'-'.substr($value, -1) : $value;
    }

    private function formatearRut(string $rut): string
    {
        return $this->normalizarRut($rut);
    }

    private function rutValido(string $rut): bool
    {
        $value = strtoupper(preg_replace('/[^0-9K]/', '', $rut) ?? '');
        if (!preg_match('/^\d{7,8}[0-9K]$/', $value)) return false;
        $sum = 0; $factor = 2;
        for ($i = strlen($value) - 2; $i >= 0; $i--) { $sum += (int) $value[$i] * $factor; $factor = $factor === 7 ? 2 : $factor + 1; }
        $digit = 11 - ($sum % 11);
        $expected = $digit === 11 ? '0' : ($digit === 10 ? 'K' : (string) $digit);
        return substr($value, -1) === $expected;
    }
}
