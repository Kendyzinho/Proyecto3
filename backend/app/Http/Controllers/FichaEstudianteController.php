<?php

namespace App\Http\Controllers;

use App\Models\Practica;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FichaEstudianteController extends Controller
{
    public function show(Practica $practica): JsonResponse
    {
        $practica->load([
            'habilitacion.estudiante.user',
            'habilitacion.tipoPractica',
            'habilitacion.periodo',
            'supervisor.centro',
            'historiales',
            'documentos' => fn ($q) => $q->whereIn('tipo', ['carta', 'seguro'])->orderByDesc('fecha'),
            'registrosDiarios',
            'dictamenes',
        ]);

        $estudiante = $practica->habilitacion?->estudiante;
        abort_unless($estudiante && $estudiante->user, 404);

        $registros = $practica->registrosDiarios;
        $minutosConfirmados = $registros->where('validacion', 'confirmado')->sum(function ($registro) {
            if (! $registro->hora_entrada || ! $registro->hora_salida) return 0;
            $entrada = strtotime('2000-01-01 '.substr((string) $registro->hora_entrada, 0, 8));
            $salida = strtotime('2000-01-01 '.substr((string) $registro->hora_salida, 0, 8));

            return max(0, (int) (($salida - $entrada) / 60));
        });
        $horasRequeridas = (int) ($practica->habilitacion->horas_requeridas ?? 0);
        $dictamen = $practica->dictamenes->sortByDesc('fecha')->first();
        $visitas = DB::table('visita_practica as relacion')
            ->join('visitas_tecnicas as visita', 'visita.id', '=', 'relacion.visita_id')
            ->where('relacion.practica_id', $practica->id)
            ->orderByDesc('visita.fecha_hora')
            ->get(['visita.id', 'visita.fecha_hora', 'visita.modalidad', 'visita.cancelada']);

        $documentos = collect(['carta', 'seguro'])->map(function (string $tipo) use ($practica) {
            $documento = $practica->documentos->firstWhere('tipo', $tipo);

            return [
                'tipo' => $tipo,
                'etiqueta' => match ($tipo) {
                    'carta' => 'Carta de presentación',
                    'seguro' => 'Seguro escolar',
                    default => 'Seguro escolar',
                },
                'registrado' => $documento !== null,
                'archivo_o_url' => $documento?->archivo_o_url,
                'fecha' => $documento?->fecha?->toIso8601String(),
                'fecha_envio' => $documento?->fecha_envio?->toIso8601String(),
            ];
        })->values();

        return response()->json(['data' => [
            'estudiante' => [
                'id' => $estudiante->id,
                'nombre' => $estudiante->user->name,
                'rut' => $estudiante->user->rut,
                'correo' => $estudiante->user->email,
                'anio_ingreso' => $estudiante->anio_ingreso,
            ],
            'practica' => [
                'id' => $practica->id,
                'tipo' => $practica->habilitacion->tipoPractica?->nombre,
                'periodo' => $practica->habilitacion->periodo?->nombre(),
                'centro' => $practica->supervisor?->centro?->nombre,
                'supervisor' => $practica->supervisor?->nombre,
                'estado' => $practica->historiales->first()?->estado_nuevo,
                'fecha_inicio' => $practica->fecha_inicio?->toDateString(),
                'fecha_fin' => $practica->fecha_fin?->toDateString(),
            ],
            'bitacora' => [
                'horas_requeridas' => $horasRequeridas,
                'minutos_confirmados' => $minutosConfirmados,
                'horas_confirmadas' => round($minutosConfirmados / 60, 2),
                'porcentaje_avance' => $horasRequeridas > 0
                    ? min(100, round($minutosConfirmados / ($horasRequeridas * 60) * 100, 1))
                    : 0,
                'jornadas' => $registros->count(),
            ],
            'documentos' => $documentos,
            'visitas' => $visitas->map(fn ($visita) => [
                'id' => $visita->id,
                'fecha_hora' => $visita->fecha_hora,
                'modalidad' => $visita->modalidad,
                'cancelada' => (bool) $visita->cancelada,
            ])->values(),
            'evaluacion' => [
                'resultado' => $dictamen?->resultado,
                'estado' => $dictamen ? ucfirst($dictamen->resultado) : 'Pendiente',
                'fecha' => $dictamen?->fecha?->toIso8601String(),
                'motivo' => $dictamen?->motivo,
            ],
        ]]);
    }
}
