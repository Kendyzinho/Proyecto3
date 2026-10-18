<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\NominaController;
use App\Models\Periodo;
use Illuminate\Support\Facades\Route;

// HU-01: máximo 6 intentos por minuto para frenar ataques de fuerza bruta
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/password', [AuthController::class, 'cambiarContrasena'])->middleware('throttle:6,1');

    // Período en curso, para las pantallas de cualquier rol
    Route::get('/periodos/actual', function () {
        $periodo = Periodo::actual();

        return response()->json($periodo ? ['id' => $periodo->id, 'nombre' => $periodo->nombre()] : null);
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

    // HU-10: carta de presentación, guía del seguro escolar y acuerdo de colaboración
    Route::middleware('rol:secretaria,coordinadora')->group(function () {
        Route::get('/documentos', [DocumentoController::class, 'index']);
        Route::get('/practicas/{practica}/documentos', [DocumentoController::class, 'show']);
        Route::get('/practicas/{practica}/documentos/{tipo}', [DocumentoController::class, 'descargar']);
        Route::post('/practicas/{practica}/documentos/enviar', [DocumentoController::class, 'enviar']);
        Route::post('/centros/{centro}/acuerdo', [DocumentoController::class, 'guardarAcuerdo']);
        Route::get('/acuerdos/{acuerdo}/archivo', [DocumentoController::class, 'archivoAcuerdo']);
    });
});
