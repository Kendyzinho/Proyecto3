<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\NominaController;
use Illuminate\Support\Facades\Route;

// HU-01: máximo 6 intentos por minuto para frenar ataques de fuerza bruta
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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
});
