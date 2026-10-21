<?php

namespace App\Http\Controllers;

use App\Mail\PracticaAsignadaMail;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AsignacionPracticaController extends Controller
{
    private const ESTADOS_QUE_OCUPAN_CUPO = ['confirmada', 'en_curso', 'finalizada'];
    private const ESTADOS_QUE_BLOQUEAN_HABILITACION = ['por_revisar', 'devuelta', 'confirmada', 'en_curso', 'finalizada'];

    public function habilitaciones(Request $request): JsonResponse
    {
        $this->requireCoordinator($request);
        $query = DB::table('habilitaciones as h')
            ->join('estudiantes as e', 'e.id', '=', 'h.estudiante_id')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->join('tipos_practica as tp', 'tp.id', '=', 'h.tipo_practica_id')
            ->join('periodos as pe', 'pe.id', '=', 'h.periodo_id')
            ->where('h.cumple_prerrequisito', true)
            ->where('tp.categoria', 'laboral')
            ->whereNull('tp.fecha_baja')
            ->whereNull('u.fecha_baja')
            ->whereNotExists(fn (Builder $sub) => $sub->selectRaw('1')
                ->from('practicas as p')
                ->whereColumn('p.habilitacion_id', 'h.id')
                ->whereExists(fn (Builder $hist) => $hist->selectRaw('1')
                    ->from('historiales_practica as hp')
                    ->whereColumn('hp.practica_id', 'p.id')
                    ->whereRaw('hp.id = (select hp2.id from historiales_practica hp2 where hp2.practica_id = p.id order by hp2.fecha_hora desc, hp2.id desc limit 1)')
                    ->whereIn('hp.estado_nuevo', self::ESTADOS_QUE_BLOQUEAN_HABILITACION)))
            ->select('h.id', 'h.estudiante_id', 'h.periodo_id', 'h.cumple_prerrequisito', 'h.horas_requeridas', 'tp.nombre as tipo_practica', 'tp.categoria', 'pe.anio', 'pe.semestre', 'u.name as estudiante', 'u.email')
            ->orderBy('u.name');

        $items = $query->get()->map(function ($item) {
            $item->areas = DB::table('estudiante_area as ea')->join('areas as a', 'a.id', '=', 'ea.area_id')
                ->where('ea.estudiante_id', $item->estudiante_id)->whereNull('a.fecha_baja')
                ->orderBy('a.nombre')->pluck('a.nombre');
            return $item;
        });

        return response()->json(['data' => $items]);
    }

    public function centros(Request $request, int $habilitacion): JsonResponse
    {
        $this->requireCoordinator($request);
        $h = DB::table('habilitaciones as h')
            ->join('tipos_practica as tp', 'tp.id', '=', 'h.tipo_practica_id')
            ->where('h.id', $habilitacion)->where('h.cumple_prerrequisito', true)
            ->where('tp.categoria', 'laboral')
            ->whereNull('tp.fecha_baja')
            ->select('h.estudiante_id', 'h.periodo_id', 'tp.categoria')->first();
        abort_if(!$h, 404, 'No se encontró una habilitación elegible.');

        $areaIds = DB::table('estudiante_area as ea')->join('areas as a', 'a.id', '=', 'ea.area_id')
            ->where('ea.estudiante_id', $h->estudiante_id)->whereNull('a.fecha_baja')->pluck('ea.area_id');
        if ($areaIds->isEmpty()) return response()->json(['data' => []]);

        $centros = DB::table('centros_practica as c')
            ->join('requerimientos_centro as rc', function ($join) use ($h) {
                $join->on('rc.centro_id', '=', 'c.id')->where('rc.periodo_id', '=', $h->periodo_id)->where('rc.categoria', '=', $h->categoria);
            })
            ->whereNotNull('c.fecha_alta')->whereNull('c.fecha_baja')
            ->whereExists(fn (Builder $area) => $area->selectRaw('1')->from('centro_area as ca')
                ->whereColumn('ca.centro_id', 'c.id')->whereIn('ca.area_id', $areaIds))
            ->whereExists(fn (Builder $supervisor) => $supervisor->selectRaw('1')->from('supervisores as s')
                ->whereColumn('s.centro_id', 'c.id')->whereNotNull('s.fecha_alta')->whereNull('s.fecha_baja'))
            ->select('c.id', 'c.razon_social', 'c.rut', 'c.direccion', 'c.es_interno', 'rc.cupos', 'rc.periodo_id', 'rc.categoria')
            ->orderBy('c.razon_social')->get();

        $centros = $centros->map(function ($centro) use ($areaIds) {
            $used = DB::table('practicas as p')
                ->join('supervisores as s', 's.id', '=', 'p.supervisor_id')
                ->where('s.centro_id', $centro->id)
                ->whereIn('p.habilitacion_id', function ($query) use ($centro) {
                    $query->select('h.id')->from('habilitaciones as h')->join('tipos_practica as tp', 'tp.id', '=', 'h.tipo_practica_id')
                        ->where('h.periodo_id', $centro->periodo_id)->where('tp.categoria', $centro->categoria);
                })
                ->whereRaw('(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = p.id order by hp.fecha_hora desc, hp.id desc limit 1) in (?, ?, ?)', self::ESTADOS_QUE_OCUPAN_CUPO)
                ->count();
            $centro->ocupados = $used;
            $centro->areas_coincidentes = DB::table('centro_area as ca')->join('areas as a', 'a.id', '=', 'ca.area_id')
                ->where('ca.centro_id', $centro->id)->whereIn('ca.area_id', $areaIds)->whereNull('a.fecha_baja')
                ->orderBy('a.nombre')->pluck('a.nombre');
            $centro->supervisores = DB::table('supervisores')->where('centro_id', $centro->id)
                ->whereNotNull('fecha_alta')->whereNull('fecha_baja')->orderBy('nombre')->get(['id', 'nombre', 'correo']);
            return $centro;
        })->filter(fn ($centro) => $centro->cupos > $centro->ocupados)->values();

        return response()->json(['data' => $centros]);
    }

    public function asignar(Request $request): JsonResponse
    {
        $this->requireCoordinator($request);
        $data = $request->validate([
            'habilitacion_id' => ['required', 'integer', 'exists:habilitaciones,id'],
            'centro_id' => ['required', 'integer', 'exists:centros_practica,id'],
            'supervisor_id' => ['required', 'integer', 'exists:supervisores,id'],
            'modalidad' => ['required', Rule::in(['presencial', 'a_distancia', 'mixta'])],
            'tareas' => ['nullable', 'string', 'max:10000'],
        ]);

        $practica = DB::transaction(function () use ($data, $request) {
            $h = DB::table('habilitaciones as h')->join('tipos_practica as tp', 'tp.id', '=', 'h.tipo_practica_id')
                ->where('h.id', $data['habilitacion_id'])->lockForUpdate()
                ->select('h.*', 'tp.categoria')->first();
            if (!$h || $h->categoria !== 'laboral') throw ValidationException::withMessages(['habilitacion_id' => 'HU-08 solo permite asignar prácticas laborales.']);
            if (!$h->cumple_prerrequisito) throw ValidationException::withMessages(['habilitacion_id' => 'El estudiante no cumple el prerrequisito.']);

            $existing = DB::table('practicas as p')->where('p.habilitacion_id', $h->id)
                ->whereRaw('(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = p.id order by hp.fecha_hora desc, hp.id desc limit 1) in (?, ?, ?, ?, ?)', self::ESTADOS_QUE_BLOQUEAN_HABILITACION)
                ->exists();
            if ($existing) throw ValidationException::withMessages(['habilitacion_id' => 'Esta habilitación ya tiene una práctica no cancelada.']);

            $center = DB::table('centros_practica')->where('id', $data['centro_id'])->lockForUpdate()->first();
            if (!$center) throw ValidationException::withMessages(['centro_id' => 'No se encontró el centro.']);
            $supervisor = DB::table('supervisores')->where('id', $data['supervisor_id'])->where('centro_id', $center->id)
                ->whereNotNull('fecha_alta')->whereNull('fecha_baja')->first();
            if (!$center->fecha_alta || $center->fecha_baja || !$supervisor) {
                throw ValidationException::withMessages(['centro_id' => 'El centro y el supervisor deben estar activos y registrados.']);
            }

            $required = DB::table('requerimientos_centro')->where('centro_id', $center->id)->where('periodo_id', $h->periodo_id)
                ->where('categoria', $h->categoria)->lockForUpdate()->first();
            if (!$required) throw ValidationException::withMessages(['centro_id' => 'El centro no tiene cupos configurados para este período y categoría.']);

            $studentAreaIds = DB::table('estudiante_area')->where('estudiante_id', $h->estudiante_id)->pluck('area_id');
            if (!DB::table('centro_area')->where('centro_id', $center->id)->whereIn('area_id', $studentAreaIds)->exists()) {
                throw ValidationException::withMessages(['centro_id' => 'No hay áreas coincidentes entre el estudiante y el centro.']);
            }

            $occupied = DB::table('practicas as p')->join('supervisores as s', 's.id', '=', 'p.supervisor_id')
                ->join('habilitaciones as h', 'h.id', '=', 'p.habilitacion_id')->join('tipos_practica as tp', 'tp.id', '=', 'h.tipo_practica_id')
                ->where('s.centro_id', $center->id)->where('h.periodo_id', $h->periodo_id)->where('tp.categoria', $h->categoria)
                ->whereRaw('(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = p.id order by hp.fecha_hora desc, hp.id desc limit 1) in (?, ?, ?)', self::ESTADOS_QUE_OCUPAN_CUPO)->count();
            if ($occupied >= $required->cupos) throw ValidationException::withMessages(['centro_id' => 'El centro ya no tiene cupos disponibles.']);

            $practiceId = DB::table('practicas')->insertGetId([
                'habilitacion_id' => $h->id, 'supervisor_id' => $supervisor->id, 'origen' => 'asignacion',
                'modalidad' => $data['modalidad'], 'tareas' => $data['tareas'] ?? null,
                'fecha_inicio' => null, 'fecha_fin' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('historiales_practica')->insert([
                'practica_id' => $practiceId, 'user_id' => $request->user()->id, 'fecha_hora' => now(),
                'estado_nuevo' => 'confirmada', 'motivo' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return DB::table('practicas')->where('id', $practiceId)->first();
        });

        $recipient = DB::table('habilitaciones as h')->join('estudiantes as e', 'e.id', '=', 'h.estudiante_id')
            ->join('users as u', 'u.id', '=', 'e.user_id')->where('h.id', $data['habilitacion_id'])->value('u.email');
        $centerName = DB::table('centros_practica')->where('id', $data['centro_id'])->value('razon_social');
        $supervisorName = DB::table('supervisores')->where('id', $data['supervisor_id'])->value('nombre');
        try {
            Mail::to($recipient)->queue(new PracticaAsignadaMail(
                $centerName,
                $supervisorName,
            ));
        } catch (Throwable $exception) {
            // El correo es secundario: un error de cola no revierte la asignación.
            Log::error('No se pudo encolar la notificación de asignación HU-08.', [
                'practica_id' => $practica->id,
                'exception' => $exception->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Práctica asignada.', 'data' => $practica], 201);
    }

    private function requireCoordinator(Request $request): void
    {
        abort_unless($request->user()?->rol === 'coordinadora', 403, 'Solo la Coordinadora puede gestionar asignaciones.');
    }
}
