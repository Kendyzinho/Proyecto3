<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListarPracticasRequest;
use App\Models\Periodo;
use App\Services\LectorPlanilla;
use App\Services\ListadoPracticasService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListadoPracticasController extends Controller
{
    public function __construct(private readonly ListadoPracticasService $listado) {}

    public function index(ListarPracticasRequest $request): JsonResponse
    {
        $periodo = Periodo::actual();

        return response()->json([
            'periodo' => $periodo ? ['id' => $periodo->id, 'nombre' => $periodo->nombre()] : null,
            'catalogo' => [
                'tipos' => $periodo ? $this->listado->catalogo($periodo)['tipos'] : [],
                'estados' => ListadoPracticasService::ESTADOS,
            ],
            'data' => $periodo ? $this->listado->listar($periodo, $request->validated()) : [],
        ]);
    }

    public function exportar(ListarPracticasRequest $request, LectorPlanilla $lector): BinaryFileResponse
    {
        $periodo = Periodo::actual();
        abort_if($periodo === null, 404, 'No hay un período académico activo.');

        return response()
            ->download(
                $this->listado->exportar($periodo, $request->validated(), $lector),
                "practicas_{$periodo->anio}-{$periodo->semestre}.xlsx"
            )
            ->deleteFileAfterSend();
    }
}
