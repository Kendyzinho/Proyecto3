<?php

namespace App\Http\Controllers;

use App\Models\Practica;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PortafolioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $estudiante = $request->user()->estudiante;
        $practicas = $estudiante ? Practica::whereHas('habilitacion', fn ($q) => $q->where('estudiante_id', $estudiante->id))
            ->with(['habilitacion.tipoPractica', 'supervisor.centro', 'documentos' => fn ($q) => $q->where('tipo', 'portafolio')->orderByDesc('id')])
            ->orderByDesc('id')->get() : collect();
        return response()->json(['data' => $practicas->map(fn ($p) => [
            'id' => $p->id, 'tipo' => $p->habilitacion->tipoPractica->nombre,
            'centro' => $p->supervisor?->centro?->nombre,
            'url' => $p->documentos->first()?->archivo_o_url,
        ])]);
    }

    public function update(Request $request, Practica $practica): JsonResponse
    {
        $estudiante = $request->user()->estudiante;
        abort_unless($estudiante && $practica->habilitacion()->where('estudiante_id', $estudiante->id)->exists(), 404);
        abort_if(count($request->allFiles()) > 0, 422, 'Solo se permite un enlace; no se aceptan archivos.');
        $datos = $request->validate(['url' => ['required', 'string', 'url:http,https', 'max:255']]);
        $documento = DB::transaction(function () use ($practica, $request, $datos) {
            Practica::whereKey($practica->id)->lockForUpdate()->firstOrFail();
            return $practica->documentos()->updateOrCreate(['tipo' => 'portafolio'], [
                'user_id' => $request->user()->id, 'archivo_o_url' => $datos['url'], 'fecha' => now(),
            ]);
        });
        return response()->json(['data' => ['url' => $documento->archivo_o_url]]);
    }
}
