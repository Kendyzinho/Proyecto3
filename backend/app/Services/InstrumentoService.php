<?php

namespace App\Services;

use App\Models\DetalleRespuesta;
use App\Models\Item;
use App\Models\Opcion;
use App\Models\PlantillaInstrumento;
use App\Models\RespuestaInstrumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Lectura y respuesta de instrumentos configurables (ER: plantilla → ítems → opciones).
// Lo usan HU-16 (pauta de visita) y HU-19 (informe confidencial); las estadísticas de HU-24/25 leen las mismas tablas.
class InstrumentoService
{
    public function plantillaVigente(string $instrumento, ?int $tipoPracticaId): ?PlantillaInstrumento
    {
        return PlantillaInstrumento::where('instrumento', $instrumento)
            ->when(
                $tipoPracticaId === null,
                fn ($q) => $q->whereNull('tipo_practica_id'),
                fn ($q) => $q->where('tipo_practica_id', $tipoPracticaId),
            )
            ->whereDate('vigente_desde', '<=', today())
            ->orderByDesc('vigente_desde')->orderByDesc('id')
            ->first();
    }

    public function formulario(PlantillaInstrumento $plantilla): array
    {
        $plantilla->loadMissing('items.opciones');

        return [
            'plantilla_id' => $plantilla->id,
            'items' => $plantilla->items->map(fn (Item $item) => [
                'id' => $item->id,
                'clave' => $item->clave,
                'enunciado' => $item->enunciado,
                'tipo' => $item->tipo_respuesta,
                'obligatorio' => $item->obligatorio,
                'seccion' => $item->seccion,
                'opciones' => $item->opciones->map(fn (Opcion $opcion) => [
                    'id' => $opcion->id,
                    'etiqueta' => $opcion->etiqueta,
                    'valor' => $opcion->valor_numerico === null ? null : (float) $opcion->valor_numerico,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    public function respuestaDe(int $practicaId, string $instrumento, ?int $visitaId = null): ?RespuestaInstrumento
    {
        return RespuestaInstrumento::where('practica_id', $practicaId)
            ->when($visitaId === null, fn ($q) => $q->whereNull('visita_id'), fn ($q) => $q->where('visita_id', $visitaId))
            ->whereHas('plantilla', fn ($q) => $q->where('instrumento', $instrumento))
            ->first();
    }

    // El ER no tiene índice único por práctica: el bloqueo de la práctica evita dos respuestas en envíos simultáneos
    public function crearRespuesta(int $practicaId, PlantillaInstrumento $plantilla, ?int $visitaId = null): RespuestaInstrumento
    {
        return DB::transaction(function () use ($practicaId, $plantilla, $visitaId) {
            DB::table('practicas')->where('id', $practicaId)->lockForUpdate()->first();

            return $this->respuestaDe($practicaId, $plantilla->instrumento, $visitaId)
                ?? RespuestaInstrumento::create(['plantilla_id' => $plantilla->id, 'practica_id' => $practicaId, 'visita_id' => $visitaId]);
        });
    }

    /**
     * Guarda lo respondido. Sin $enviar es un borrador y puede quedar incompleto;
     * al enviar exige las obligatorias y la respuesta ya no se puede modificar.
     *
     * @param  array<int, array{item_id: int, opcion_id?: int|null, texto?: string|null}>  $respuestas
     */
    public function guardar(RespuestaInstrumento $respuesta, array $respuestas, bool $enviar): RespuestaInstrumento
    {
        return DB::transaction(function () use ($respuesta, $respuestas, $enviar) {
            // Mismo orden de bloqueo que crearRespuesta: primero la práctica, después la respuesta
            DB::table('practicas')->where('id', $respuesta->practica_id)->lockForUpdate()->first();
            $respuesta = RespuestaInstrumento::whereKey($respuesta->id)->lockForUpdate()->firstOrFail();
            abort_if($respuesta->enviada(), 409, 'Ya fue enviado y no se puede modificar.');

            $items = Item::with('opciones')->where('plantilla_id', $respuesta->plantilla_id)->get()->keyBy('id');
            $valores = $this->valores($items, $respuestas);

            if ($enviar) {
                $faltantes = $items->filter(fn (Item $item) => $item->obligatorio && ! isset($valores[$item->id]));
                if ($faltantes->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'respuestas' => 'Responde todas las preguntas obligatorias antes de enviar.',
                        'faltantes' => $faltantes->keys()->map(fn ($id) => (string) $id)->values()->all(),
                    ]);
                }
            }

            // El borrador queda exactamente como lo dejó quien responde: lo que ya no viene se borra
            DetalleRespuesta::where('respuesta_id', $respuesta->id)->whereNotIn('item_id', array_keys($valores) ?: [0])->delete();
            foreach ($valores as $itemId => $valor) {
                DetalleRespuesta::updateOrCreate(['respuesta_id' => $respuesta->id, 'item_id' => $itemId], $valor);
            }

            $enviar ? $respuesta->update(['fecha_envio' => now()]) : $respuesta->touch();

            return $respuesta->fresh();
        });
    }

    public function leer(RespuestaInstrumento $respuesta): array
    {
        $respuesta->loadMissing(['plantilla', 'detalles']);

        return $this->formulario($respuesta->plantilla) + [
            'respuesta_id' => $respuesta->id,
            'enviada_en' => $respuesta->fecha_envio?->toIso8601String(),
            'respuestas' => $respuesta->detalles->map(fn (DetalleRespuesta $detalle) => [
                'item_id' => $detalle->item_id,
                'opcion_id' => $detalle->opcion_id,
                'texto' => $detalle->texto,
            ])->values()->all(),
        ];
    }

    // Valida cada fila contra la plantilla y la deja lista para guardar; lo que viene vacío cuenta como sin responder
    private function valores($items, array $respuestas): array
    {
        $valores = [];
        foreach (array_values($respuestas) as $i => $fila) {
            $item = $items->get((int) ($fila['item_id'] ?? 0));
            if (! $item) {
                throw ValidationException::withMessages(["respuestas.$i.item_id" => 'La pregunta no pertenece a este formulario.']);
            }
            $opcionId = $fila['opcion_id'] ?? null;
            $texto = trim((string) ($fila['texto'] ?? ''));

            if ($item->seRespondeConTexto()) {
                if ($opcionId !== null) {
                    throw ValidationException::withMessages(["respuestas.$i.opcion_id" => 'Esta pregunta se responde con texto.']);
                }
                if ($texto !== '') {
                    $valores[$item->id] = ['opcion_id' => null, 'texto' => $texto];
                }

                continue;
            }

            if ($texto !== '') {
                throw ValidationException::withMessages(["respuestas.$i.texto" => 'Esta pregunta se responde eligiendo una opción.']);
            }
            if ($opcionId === null) {
                continue;
            }
            if (! $item->opciones->contains('id', (int) $opcionId)) {
                throw ValidationException::withMessages(["respuestas.$i.opcion_id" => 'Elige una opción válida.']);
            }
            $valores[$item->id] = ['opcion_id' => (int) $opcionId, 'texto' => null];
        }

        return $valores;
    }
}
