<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarPautaRequest;
use App\Models\Practica;
use App\Models\VisitaTecnica;
use App\Services\PautaVisitaService;
use Illuminate\Http\JsonResponse;

// HU-16: pauta de la visita técnica
class PautaVisitaController extends Controller
{
    public function __construct(private readonly PautaVisitaService $service) {}

    public function index(VisitaTecnica $visita): JsonResponse
    {
        return response()->json(['data' => $this->service->pautas($visita)]);
    }

    public function update(GuardarPautaRequest $request, VisitaTecnica $visita, Practica $practica): JsonResponse
    {
        $enviar = $request->boolean('enviar');
        $pauta = $this->service->guardar($visita, $practica, $request->validated('respuestas'), $enviar);

        return response()->json(['data' => $pauta, 'message' => $enviar ? 'Pauta enviada.' : 'Borrador guardado.']);
    }

    public function dePractica(Practica $practica): JsonResponse
    {
        return response()->json(['data' => $this->service->enviadasDe($practica)]);
    }
}
