<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarCalendarioRequest;
use App\Models\Periodo;
use App\Services\CalendarioPracticaService;
use Illuminate\Http\JsonResponse;

class CalendarioPracticaController extends Controller
{
    public function index(CalendarioPracticaService $service): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json(['data' => $periodo ? $service->listar($periodo) : []]);
    }

    public function update(GuardarCalendarioRequest $request, int $practica, CalendarioPracticaService $service): JsonResponse
    {
        $service->guardar($service->buscarEditable($practica), $request->validated());

        return response()->json(['message' => 'Fechas y horario guardados.']);
    }
}
