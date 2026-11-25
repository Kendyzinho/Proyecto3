<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertaResource;
use App\Models\Alerta;
use App\Services\AlertaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// HU-14: panel de alertas de la Coordinadora
class AlertaController extends Controller
{
    public function __construct(private readonly AlertaService $alertas)
    {
    }

    // ?estado=abiertas (por defecto) o ?estado=atendidas
    public function index(Request $request): AnonymousResourceCollection
    {
        $atendidas = $request->query('estado') === 'atendidas';

        return AlertaResource::collection($this->alertas->listar($atendidas))
            ->additional(['meta' => $this->alertas->contar()]);
    }

    public function atender(Request $request, Alerta $alerta): AlertaResource
    {
        return new AlertaResource($this->alertas->atender($alerta, $request->user())->load('coordinadora'));
    }
}
