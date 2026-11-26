<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarRespuestasRequest;
use App\Models\Practica;
use App\Services\InformeConfidencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// HU-19: el supervisor(a) responde con su cuenta los informes que le pidieron de sus estudiantes
class SupervisorInformeController extends Controller
{
    public function __construct(private readonly InformeConfidencialService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->delSupervisor($request->user())]);
    }

    public function show(Request $request, Practica $practica): JsonResponse
    {
        return response()->json(['data' => $this->service->paraSupervisor($request->user(), $practica)]);
    }

    public function update(GuardarRespuestasRequest $request, Practica $practica): JsonResponse
    {
        $enviar = $request->boolean('enviar');
        $informe = $this->service->guardar($request->user(), $practica, $request->validated('respuestas'), $enviar);

        return response()->json(['data' => $informe, 'message' => $enviar ? 'Informe enviado.' : 'Borrador guardado.']);
    }
}
