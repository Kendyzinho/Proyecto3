<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarSemanaRequest;
use App\Models\Practica;
use App\Services\ConfirmacionSemanalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// HU-26: el supervisor confirma con su cuenta la asistencia semanal de sus estudiantes
class ConfirmacionSemanalController extends Controller
{
    public function __construct(private readonly ConfirmacionSemanalService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->service->delSupervisor($request->user())]);
    }

    public function show(Request $request, Practica $practica, string $semana): JsonResponse
    {
        return response()->json(['data' => $this->service->paraSupervisor($request->user(), $practica, $semana)]);
    }

    public function store(ConfirmarSemanaRequest $request, Practica $practica, string $semana): JsonResponse
    {
        $detalle = $this->service->confirmar($request->user(), $practica, $semana, $request->validated());

        return response()->json(['data' => $detalle, 'message' => 'Confirmación enviada.']);
    }
}