<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarUsuarioRequest;
use App\Http\Resources\CuentaResource;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// HU-05: administración de cuentas (Secretaria de Jefatura)
class UsuarioController extends Controller
{
    public function __construct(private readonly UsuarioService $usuarios)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        return CuentaResource::collection($this->usuarios->listar());
    }

    public function store(GuardarUsuarioRequest $request): JsonResponse
    {
        return (new CuentaResource($this->usuarios->crear($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(GuardarUsuarioRequest $request, User $usuario): CuentaResource
    {
        return new CuentaResource($this->usuarios->editar($usuario, $request->validated(), $request->user()));
    }

    public function desactivar(Request $request, User $usuario): CuentaResource
    {
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'max:255']],
            ['motivo.required' => 'Elige el motivo.'],
        );

        return new CuentaResource($this->usuarios->desactivar($usuario, $datos['motivo'], $request->user()));
    }

    public function reactivar(User $usuario): CuentaResource
    {
        return new CuentaResource($this->usuarios->reactivar($usuario));
    }
}

