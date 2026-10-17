<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgregarEstudianteRequest;
use App\Http\Requests\PlanillaNominaRequest;
use App\Models\Periodo;
use App\Services\LectorPlanilla;
use App\Services\NominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

// HU-07: carga de estudiantes y verificación de prerrequisitos (Secretaria de Jefatura)
class NominaController extends Controller
{
    public function __construct(private readonly NominaService $nomina)
    {
    }

    // Períodos y prácticas de la malla vigente, para los selectores de la pantalla
    public function catalogo(): JsonResponse
    {
        $periodos = Periodo::orderByDesc('anio')->orderByDesc('semestre')->get();
        $semestreActual = now()->month >= 7 ? 2 : 1;
        $actual = $periodos->first(fn ($p) => $p->anio === (int) now()->year && $p->semestre === $semestreActual) ?? $periodos->first();

        return response()->json([
            'periodos' => $periodos->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->nombre()]),
            'periodo_actual_id' => $actual?->id,
            'practicas' => $this->nomina->tiposVigentes()->map(fn ($t) => [
                'id' => $t->id, 'nombre' => $t->nombre, 'prerrequisito' => $t->prerrequisito,
            ])->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $periodo = Periodo::findOrFail($request->integer('periodo_id'));

        return response()->json(['data' => $this->nomina->listar($periodo)]);
    }

    public function plantilla(LectorPlanilla $lector): BinaryFileResponse
    {
        return response()->download($lector->crearPlantilla(), 'plantilla_nomina.xlsx')->deleteFileAfterSend();
    }

    public function revisar(PlanillaNominaRequest $request): JsonResponse
    {
        return response()->json(
            $this->nomina->revisar($request->file('archivo'), Periodo::findOrFail($request->integer('periodo_id')))
        );
    }

    public function confirmar(PlanillaNominaRequest $request): JsonResponse
    {
        $resultado = $this->nomina->confirmar(
            $request->file('archivo'),
            Periodo::findOrFail($request->integer('periodo_id')),
            $request->user(),
        );

        return response()->json($resultado, 201);
    }

    public function agregar(AgregarEstudianteRequest $request): JsonResponse
    {
        $habilitacion = $this->nomina->agregar($request->validated(), Periodo::findOrFail($request->integer('periodo_id')));

        return response()->json(['id' => $habilitacion->id], 201);
    }
}
