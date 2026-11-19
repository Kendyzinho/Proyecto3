<?php

namespace App\Http\Controllers;

use App\Models\Practica;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PercepcionCentroController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $estudianteId = $request->user()->estudiante?->id;
        $plantillaId = $this->plantillaId();
        $practicas = $estudianteId ? Practica::query()
            ->whereHas('habilitacion', fn ($q) => $q->where('estudiante_id', $estudianteId))
            ->with(['supervisor.centro', 'habilitacion.tipoPractica', 'historiales'])
            ->orderByDesc('id')->get() : collect();

        return response()->json(['data' => $practicas->map(fn (Practica $p) => [
            'id' => $p->id,
            'tipo' => $p->habilitacion->tipoPractica?->nombre,
            'centro' => $p->supervisor?->centro?->razon_social,
            'estado' => $p->historiales->first()?->estado_nuevo,
            'percepcion' => $plantillaId ? $this->leerRespuesta($p->id, $plantillaId) : null,
        ])]);
    }

    public function store(Request $request, Practica $practica): JsonResponse
    {
        $estudianteId = $request->user()->estudiante?->id;
        abort_unless($estudianteId && $practica->habilitacion()->where('estudiante_id', $estudianteId)->exists(), 404);
        abort_unless($practica->supervisor_id && Practica::query()->whereKey($practica->id)->conEstado(['confirmada', 'en_curso', 'finalizada'])->exists(), 422, 'La práctica aún no está confirmada.');

        $datos = $request->validate([
            'clima_laboral' => ['required', 'integer', 'between:1,5'],
            'pago_viatico' => ['required', 'integer', 'between:1,5'],
            'pertinencia_tareas' => ['required', 'integer', 'between:1,5'],
            'observaciones' => ['nullable', 'string', 'max:3000'],
        ]);

        $plantillaId = $this->plantillaId();
        abort_unless($plantillaId, 503, 'La encuesta todavía no está configurada.');
        $items = DB::table('items')->where('plantilla_id', $plantillaId)
            ->whereIn('clave', ['clima_laboral', 'pago_viatico', 'pertinencia_tareas', 'observaciones'])
            ->get()->keyBy('clave');
        abort_unless($items->count() === 4, 503, 'La encuesta está incompleta.');

        DB::transaction(function () use ($practica, $plantillaId, $items, $datos) {
            // Serializa los envíos simultáneos de una misma práctica; el esquema compartido no tiene índice único aquí.
            DB::table('practicas')->where('id', $practica->id)->lockForUpdate()->first();
            $respuesta = DB::table('respuestas_instrumento')->where('plantilla_id', $plantillaId)
                ->where('practica_id', $practica->id)->lockForUpdate()->first();
            $respuestaId = $respuesta?->id ?? DB::table('respuestas_instrumento')->insertGetId([
                'plantilla_id' => $plantillaId, 'practica_id' => $practica->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (['clima_laboral', 'pago_viatico', 'pertinencia_tareas'] as $clave) {
                $opcionId = DB::table('opciones')->where('item_id', $items[$clave]->id)
                    ->where('valor_numerico', $datos[$clave])->value('id');
                abort_unless($opcionId, 503, 'Faltan opciones de la encuesta.');
                DB::table('detalles_respuesta')->updateOrInsert(
                    ['respuesta_id' => $respuestaId, 'item_id' => $items[$clave]->id],
                    ['opcion_id' => $opcionId, 'texto' => null, 'updated_at' => now()],
                );
            }
            DB::table('detalles_respuesta')->updateOrInsert(
                ['respuesta_id' => $respuestaId, 'item_id' => $items['observaciones']->id],
                ['opcion_id' => null, 'texto' => $datos['observaciones'] ?? null, 'updated_at' => now()],
            );
            DB::table('respuestas_instrumento')->where('id', $respuestaId)->update(['fecha_envio' => now(), 'updated_at' => now()]);
        });

        return response()->json(['data' => $this->leerRespuesta($practica->id, $plantillaId)]);
    }

    private function plantillaId(): ?int
    {
        return DB::table('plantillas_instrumento as plantilla')
            ->join('items as item', 'item.plantilla_id', '=', 'plantilla.id')
            ->where('plantilla.instrumento', 'encuesta')->whereNull('plantilla.tipo_practica_id')
            ->where('item.clave', 'clima_laboral')->orderByDesc('plantilla.vigente_desde')
            ->value('plantilla.id');
    }

    private function leerRespuesta(int $practicaId, int $plantillaId): ?array
    {
        $respuesta = DB::table('respuestas_instrumento')->where('practica_id', $practicaId)
            ->where('plantilla_id', $plantillaId)->whereNotNull('fecha_envio')->first();
        if (! $respuesta) return null;
        $detalles = DB::table('detalles_respuesta as detalle')
            ->join('items as item', 'item.id', '=', 'detalle.item_id')
            ->leftJoin('opciones as opcion', 'opcion.id', '=', 'detalle.opcion_id')
            ->where('detalle.respuesta_id', $respuesta->id)
            ->select('item.clave', 'opcion.valor_numerico', 'detalle.texto')->get()->keyBy('clave');

        return [
            'clima_laboral' => (int) ($detalles['clima_laboral']->valor_numerico ?? 0),
            'pago_viatico' => (int) ($detalles['pago_viatico']->valor_numerico ?? 0),
            'pertinencia_tareas' => (int) ($detalles['pertinencia_tareas']->valor_numerico ?? 0),
            'observaciones' => $detalles['observaciones']->texto ?? null,
        ];
    }
}
