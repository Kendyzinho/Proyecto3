<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Deja pasar solo a los roles indicados en la ruta. Uso: ->middleware('rol:secretaria,coordinadora')
// El estudiante tiene rol vacío en la base; aquí se llama "estudiante".
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();
        abort_if($usuario === null, 401);

        if (! in_array($usuario->rol ?? 'estudiante', $roles, true)) {
            abort(403, 'No tienes permiso para esta acción.');
        }

        return $next($request);
    }
}
