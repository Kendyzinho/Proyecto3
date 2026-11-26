<?php

namespace App\Http\Controllers;

use App\Models\Periodo;
use App\Models\Practica;
use App\Services\InformeConfidencialService;
use Illuminate\Http\JsonResponse;

// HU-19: la Coordinadora o el Jefe piden el informe confidencial y lo leen cuando el supervisor(a) lo envía
class InformeConfidencialController extends Controller
{
    public function __construct(private readonly InformeConfidencialService $service) {}

    public function index(): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json(['data' => $periodo ? $this->service->listar($periodo) : []]);
    }

    public function solicitar(Practica $practica): JsonResponse
    {
        [$fila, $mensaje] = $this->service->solicitar($practica);

        return response()->json(['data' => $fila, 'message' => $mensaje]);
    }

    public function show(Practica $practica): JsonResponse
    {
        return response()->json(['data' => $this->service->enviado($practica)]);
    }
}
