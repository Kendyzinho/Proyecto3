<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CentroPracticaController;
use Illuminate\Support\Facades\Route;

// HU-01: máximo 6 intentos por minuto para frenar ataques de fuerza bruta
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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
