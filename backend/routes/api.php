<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AsignacionPracticaController;
use App\Http\Controllers\CentroPracticaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\PracticaExcepcionalController;
use App\Http\Controllers\SolicitudPracticaController;
use App\Http\Controllers\UsuarioController;
use App\Models\Periodo;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MallaController;



// HU-01: máximo 6 intentos por minuto para frenar ataques de fuerza bruta
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('rol:coordinadora')->prefix('coordinacion')->group(function () {
        Route::get('/metricas', [PracticaExcepcionalController::class, 'metricas']);
        Route::get('/practicas-excepcionales/catalogo', [PracticaExcepcionalController::class, 'catalogo']);
        Route::post('/practicas-excepcionales', [PracticaExcepcionalController::class, 'store']);

        // HU-08: asignación de prácticas laborales por perfil.
        Route::get('/asignaciones/habilitaciones', [AsignacionPracticaController::class, 'habilitaciones']);
        Route::get('/asignaciones/habilitaciones/{habilitacion}/centros', [AsignacionPracticaController::class, 'centros']);
        Route::post('/asignaciones', [AsignacionPracticaController::class, 'asignar']);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'cambiarContrasena'])->middleware('throttle:6,1');

    // HU-09: solicitud profesional del estudiante y revisión por Coordinación.
    Route::get('/practicas/solicitud-profesional', [SolicitudPracticaController::class, 'estudiante']);
    Route::post('/practicas/solicitud-profesional', [SolicitudPracticaController::class, 'guardar']);
    Route::get('/solicitudes-practica', [SolicitudPracticaController::class, 'bandeja']);
    Route::post('/practicas/{practica}/decision', [SolicitudPracticaController::class, 'decidir']);

    Route::get('/periodos/actual', function () {
        $periodo = Periodo::actual();

        return response()->json($periodo ? ['id' => $periodo->id, 'nombre' => $periodo->nombre()] : null);
    });

    // HU-05: cuentas de usuario, solo la Secretaria de Jefatura.
    Route::middleware('rol:secretaria')->prefix('usuarios')->group(function () {
        Route::get('/', [UsuarioController::class, 'index']);
        Route::post('/', [UsuarioController::class, 'store']);
        Route::put('/{usuario}', [UsuarioController::class, 'update']);
        Route::post('/{usuario}/desactivar', [UsuarioController::class, 'desactivar']);
        Route::post('/{usuario}/reactivar', [UsuarioController::class, 'reactivar']);
    });
    // HU-06: mallas y prácticas (Jefe de Carrera)
Route::middleware('rol:jefe')->group(function () {
        Route::get('/mallas', [MallaController::class, 'index']);
        Route::post('/mallas', [MallaController::class, 'store']);
        Route::post('/mallas/{malla}/publicar', [MallaController::class, 'publicar']);
        Route::post('/mallas/{malla}/practicas', [MallaController::class, 'agregarPractica']);
        Route::put('/tipos-practica/{tipo}', [MallaController::class, 'editarPractica']);
        Route::delete('/tipos-practica/{tipo}', [MallaController::class, 'quitarPractica']);
    });

    // HU-07: nómina de estudiantes, solo la Secretaria de Jefatura
    Route::middleware('rol:secretaria')->prefix('nomina')->group(function () {
        Route::get('/', [NominaController::class, 'index']);
        Route::get('/catalogo', [NominaController::class, 'catalogo']);
        Route::get('/plantilla', [NominaController::class, 'plantilla']);
        Route::get('/exportar', [NominaController::class, 'exportar']);
        Route::post('/revisar', [NominaController::class, 'revisar']);
        Route::post('/confirmar', [NominaController::class, 'confirmar']);
        Route::post('/estudiantes', [NominaController::class, 'agregar']);
    });

    // HU-10: documentos de prácticas y acuerdos de colaboración.
    Route::middleware('rol:secretaria,coordinadora')->group(function () {
        Route::get('/documentos', [DocumentoController::class, 'index']);
        Route::get('/practicas/{practica}/documentos', [DocumentoController::class, 'show']);
        Route::get('/practicas/{practica}/documentos/{tipo}', [DocumentoController::class, 'descargar']);
        Route::post('/practicas/{practica}/documentos/enviar', [DocumentoController::class, 'enviar']);
        Route::post('/centros/{centro}/acuerdo', [DocumentoController::class, 'guardarAcuerdo']);
        Route::get('/acuerdos/{acuerdo}/archivo', [DocumentoController::class, 'archivoAcuerdo']);
    });

    // HU-12: catálogo de centros; las mutaciones y bajas verifican rol coordinadora.
    Route::get('/areas', [CentroPracticaController::class, 'areas']);
    Route::post('/areas', [CentroPracticaController::class, 'storeArea']);
    Route::get('/periodos', [CentroPracticaController::class, 'periodos']);
    Route::get('/centros-practica', [CentroPracticaController::class, 'index']);
    Route::get('/centros-practica/{centroPractica}', [CentroPracticaController::class, 'show']);
    Route::post('/centros-practica', [CentroPracticaController::class, 'store']);
    Route::put('/centros-practica/{centroPractica}', [CentroPracticaController::class, 'update']);
    Route::post('/centros-practica/{centroPractica}/dar-de-baja', [CentroPracticaController::class, 'darDeBaja']);
    Route::post('/centros-practica/{centroPractica}/reactivar', [CentroPracticaController::class, 'reactivar']);
});
