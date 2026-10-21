<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AsignacionPracticaController;
use Illuminate\Support\Facades\Route;

// HU-01: máximo 6 intentos por minuto para frenar ataques de fuerza bruta
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // HU-08: solo la Coordinadora puede consultar habilitaciones/centros y asignar.
    Route::get('/coordinacion/asignaciones/habilitaciones', [AsignacionPracticaController::class, 'habilitaciones']);
    Route::get('/coordinacion/asignaciones/habilitaciones/{habilitacion}/centros', [AsignacionPracticaController::class, 'centros']);
    Route::post('/coordinacion/asignaciones', [AsignacionPracticaController::class, 'asignar']);
});
