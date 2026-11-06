<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarTipoPracticaRequest;
use App\Http\Resources\MallaResource;
use App\Http\Resources\TipoPracticaResource;
use App\Models\Malla;
use App\Models\TipoPractica;
use App\Services\MallaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

//  mallas y prácticas (Jefe de Carrera)
class MallaController extends Controller
{
    public function __construct(private readonly MallaService $mallas)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return MallaResource::collection($this->mallas->listar());
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate(
            ['nombre' => ['required', 'string', 'max:100']],
            ['nombre.required' => 'Ingresa el nombre de la malla.'],
        );

        return (new MallaResource($this->mallas->crear($datos['nombre'])))
            ->response()
            ->setStatusCode(201);
    }

    public function publicar(Request $request, Malla $malla): MallaResource
    {
        $datos = $request->validate(
            ['vigente_desde' => ['required', 'date_format:Y-m-d', 'after_or_equal:today']],
            [
                'vigente_desde.required' => 'Indica desde cuándo rige la malla.',
                'vigente_desde.date_format' => 'La fecha no es válida.',
                'vigente_desde.after_or_equal' => 'La fecha no puede ser anterior a hoy.',
            ],
        );

        return new MallaResource($this->mallas->publicar($malla, $datos['vigente_desde']));
    }

    public function agregarPractica(GuardarTipoPracticaRequest $request, Malla $malla): JsonResponse
    {
        return (new TipoPracticaResource($this->mallas->agregarPractica($malla, $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function editarPractica(GuardarTipoPracticaRequest $request, TipoPractica $tipo): TipoPracticaResource
    {
        return new TipoPracticaResource($this->mallas->editarPractica($tipo, $request->validated()));
    }

    public function quitarPractica(TipoPractica $tipo): Response
    {
        $this->mallas->quitarPractica($tipo);

        return response()->noContent();
    }
}
