<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarVisitaRequest;
use App\Models\Periodo;
use App\Models\VisitaTecnica;
use App\Services\VisitaTecnicaService;
use Illuminate\Http\JsonResponse;

// HU-15: programación y agenda de visitas técnicas
class VisitaTecnicaController extends Controller
{
    public function __construct(private readonly VisitaTecnicaService $service) {}

    public function index(): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json(['data' => $periodo ? $this->service->listar($periodo) : []]);
    }

    public function sinVisita(): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json(['data' => $periodo ? $this->service->centrosSinVisita($periodo) : []]);
    }

    public function disponibles(): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json(['data' => $periodo ? $this->service->practicasDisponibles($periodo) : []]);
    }

    public function store(GuardarVisitaRequest $request): JsonResponse
    {
        $visita = $this->service->crear($request->validated(), $request->user());

        return response()->json(['data' => $this->service->serializar($visita), 'message' => 'Visita agendada.'], 201);
    }

    public function update(GuardarVisitaRequest $request, VisitaTecnica $visita): JsonResponse
    {
        $visita = $this->service->editar($visita, $request->validated());

        return response()->json(['data' => $this->service->serializar($visita->fresh()), 'message' => 'Visita actualizada.']);
    }

    public function cancelar(VisitaTecnica $visita): JsonResponse
    {
        $visita = $this->service->cancelar($visita);

        return response()->json(['data' => $this->service->serializar($visita), 'message' => 'Visita cancelada.']);
    }
}
