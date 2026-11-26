<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmarSemanaRequest;
use App\Services\ConfirmacionSemanalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfirmacionSemanalController extends Controller
{
    public function show(Request $request, int $practica, string $semana, ConfirmacionSemanalService $service): JsonResponse
    {
        [$modelo, $lunes] = $service->delEnlace($practica, $semana, $request->integer('supervisor'));

        return response()->json(['data' => $service->detalle($modelo, $lunes)]);
    }

    public function store(ConfirmarSemanaRequest $request, int $practica, string $semana, ConfirmacionSemanalService $service): JsonResponse
    {
        [$modelo, $lunes] = $service->delEnlace($practica, $semana, $request->integer('supervisor'));
        $service->confirmar($modelo, $lunes, $request->validated());

        return response()->json(['data' => $service->detalle($modelo, $lunes), 'message' => 'Confirmación enviada.'], 201);
    }
}
