<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtenderAlertaRequest;
use App\Http\Resources\AlertaResource;
use App\Http\Resources\NotificacionResource;
use App\Models\Alerta;
use App\Models\Practica;
use App\Services\AlertaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// HU-14: alertas tempranas en la campana de cada rol y en la ficha del estudiante
class AlertaController extends Controller
{
    public function __construct(private readonly AlertaService $alertas)
    {
    }

    public function notificaciones(Request $request): JsonResponse
    {
        $lineas = $this->alertas->notificaciones($request->user());

        return response()->json([
            'data' => NotificacionResource::collection($lineas)->resolve($request),
            'meta' => ['total' => $lineas->count()],
        ]);
    }

    public function dePractica(Request $request, Practica $practica): JsonResponse
    {
        $alertas = $this->alertas->dePractica($practica);

        return response()->json(['data' => [
            'pendientes' => AlertaResource::collection($alertas['pendientes'])->resolve($request),
            'historial' => AlertaResource::collection($alertas['historial'])->resolve($request),
        ]]);
    }

    public function atender(AtenderAlertaRequest $request, Alerta $alerta): AlertaResource
    {
        return new AlertaResource($this->alertas->atender($alerta, $request->user(), $request->validated('nota')));
    }
}
