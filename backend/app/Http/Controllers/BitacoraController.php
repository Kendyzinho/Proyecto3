<?php

namespace App\Http\Controllers;

use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class BitacoraController extends Controller
{
    public function index(Request $request, BitacoraService $service): JsonResponse
    {
        return response()->json(['data' => $service->listar($request->user())]);
    }

    public function show(Request $request, int $practica, BitacoraService $service): JsonResponse
    {
        return response()->json(['data' => $service->detalle($request->user(), $practica)]);
    }

    public function guardar(Request $request, int $practica, string $fecha, BitacoraService $service): JsonResponse
    {
        Validator::validate(['fecha' => $fecha], ['fecha' => ['required', 'date_format:Y-m-d']]);
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['jornada', 'inasistencia'])],
            'hora_entrada' => ['required_if:tipo,jornada', 'nullable', 'date_format:H:i'],
            'hora_salida' => ['required_if:tipo,jornada', 'nullable', 'date_format:H:i', 'after:hora_entrada'],
            'actividades' => ['required_if:tipo,jornada', 'nullable', 'string', 'max:5000', 'regex:/\S/u'],
            'justificacion' => ['required_if:tipo,inasistencia', 'nullable', 'string', 'max:3000', 'regex:/\S/u'],
            'recuperacion_programada' => ['required_if:tipo,inasistencia', 'nullable', 'date_format:Y-m-d'],
        ], [
            'hora_salida.after' => 'La hora de salida debe ser posterior a la hora de entrada.',
            'actividades.regex' => 'Describe las actividades realizadas.',
            'justificacion.regex' => 'Escribe el motivo de la inasistencia.',
        ]);

        if ($datos['tipo'] === 'inasistencia') {
            $registro = $service->informarInasistencia($request->user(), $practica, $fecha, $datos);
        } else {
            $registro = $service->guardarJornada($request->user(), $practica, $fecha, $datos);
        }

        return response()->json(['data' => $registro]);
    }
}
