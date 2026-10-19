<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistrarPracticaExcepcionalRequest;
use App\Models\Periodo;
use App\Models\Practica;
use App\Services\PracticaExcepcionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PracticaExcepcionalController extends Controller
{
    public function catalogo(Request $request, PracticaExcepcionalService $service): JsonResponse
    {
        $datos = $request->validate(['periodo_id' => ['required', 'integer', 'exists:periodos,id']]);

        return response()->json($service->catalogo(Periodo::findOrFail($datos['periodo_id'])));
    }

    public function store(RegistrarPracticaExcepcionalRequest $request, PracticaExcepcionalService $service): JsonResponse
    {
        $practica = $service->registrar($request->validated(), $request->user());

        return response()->json(['data' => [
            'id' => $practica->id, 'origen' => $practica->origen,
            'registrada_por' => $practica->registrada_por, 'created_at' => $practica->created_at,
        ]], 201);
    }

    public function metricas(): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json([
            'periodo' => $periodo ? ['id' => $periodo->id, 'nombre' => $periodo->nombre()] : null,
            'practicas_registradas' => $periodo ? Practica::whereHas('habilitacion', fn ($q) => $q->where('periodo_id', $periodo->id))->count() : 0,
        ]);
    }
}
