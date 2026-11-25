<?php

use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\PortafolioController;
use App\Http\Controllers\AsignacionPracticaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarioPracticaController;
use App\Http\Controllers\CentroPracticaController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\ListadoPracticasController;
use App\Http\Controllers\MallaController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\PracticaExcepcionalController;
use App\Http\Controllers\PercepcionCentroController;
use App\Http\Controllers\SolicitudPracticaController;
use App\Http\Controllers\UsuarioController;
use App\Models\Periodo;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MallaController;
use App\Http\Controllers\ConfirmacionSemanalController;
use App\Http\Controllers\AlertaController;




// HU-01: máximo 6 intentos por minuto para frenar ataques de fuerza bruta
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

// HU-02: recuperación de contraseña sin sesión iniciada
Route::post('/recuperar-contrasena', [AuthController::class, 'solicitarRecuperacion'])->middleware('throttle:6,1');
Route::post('/restablecer-contrasena', [AuthController::class, 'restablecerContrasena'])->middleware('throttle:6,1');
// HU-26: el supervisor no tiene cuenta; la firma del enlace reemplaza la sesión.
// GET y POST comparten URI, así que la misma firma sirve para ver y para confirmar.
Route::middleware(['signed:relative', 'throttle:30,1'])->prefix('supervision')->group(function () {
    Route::get('/practicas/{practica}/semanas/{semana}', [ConfirmacionSemanalController::class, 'show'])
        ->whereNumber('practica')->where('semana', '\d{4}-\d{2}-\d{2}')->name('supervision.semana');
    Route::post('/practicas/{practica}/semanas/{semana}', [ConfirmacionSemanalController::class, 'store'])
        ->whereNumber('practica')->where('semana', '\d{4}-\d{2}-\d{2}');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::middleware('rol:estudiante')->group(function () {
        // HU-13: registro diario de asistencia, actividades, inasistencias y progreso.
        Route::get('/estudiante/bitacora', [BitacoraController::class, 'index']);
        Route::get('/estudiante/practicas/{practica}/bitacora', [BitacoraController::class, 'show']);
        Route::put('/estudiante/practicas/{practica}/bitacora/{fecha}', [BitacoraController::class, 'guardar']);
        Route::get('/estudiante/portafolios', [PortafolioController::class, 'index']);
        Route::put('/estudiante/practicas/{practica}/portafolio', [PortafolioController::class, 'update']);
        Route::get('/estudiante/percepciones-centro', [PercepcionCentroController::class, 'index']);
        Route::put('/estudiante/practicas/{practica}/percepcion-centro', [PercepcionCentroController::class, 'store']);
    });
    // El Jefe de Carrera puede hacer todo lo que hacen la Coordinadora y la Secretaría (reunión 4)
    Route::middleware('rol:coordinadora,jefe')->prefix('coordinacion')->group(function () {
        Route::get('/metricas', [PracticaExcepcionalController::class, 'metricas']);
        Route::get('/practicas-excepcionales/catalogo', [PracticaExcepcionalController::class, 'catalogo']);
        Route::post('/practicas-excepcionales', [PracticaExcepcionalController::class, 'store']);

        // HU-08: asignación de prácticas laborales por perfil.
        Route::get('/asignaciones/habilitaciones', [AsignacionPracticaController::class, 'habilitaciones']);
        Route::get('/asignaciones/habilitaciones/{habilitacion}/centros', [AsignacionPracticaController::class, 'centros']);
        Route::put('/asignaciones/habilitaciones/{habilitacion}/areas', [AsignacionPracticaController::class, 'actualizarAreas']);
        Route::post('/asignaciones', [AsignacionPracticaController::class, 'asignar']);

        // Fechas y horario de las prácticas confirmadas (Art. 17)
        Route::get('/calendarios', [CalendarioPracticaController::class, 'index']);
                // HU-14: alertas tempranas (luces rojas y amarillas)
        Route::get('/alertas', [AlertaController::class, 'index']);
        Route::post('/alertas/{alerta}/atender', [AlertaController::class, 'atender']);
        Route::put('/calendarios/{practica}', [CalendarioPracticaController::class, 'update']);

        Route::get('/practicas', [ListadoPracticasController::class, 'index']);
        Route::get('/practicas/exportar', [ListadoPracticasController::class, 'exportar']);
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

    // HU-05: cuentas de usuario (Secretaria de Jefatura y Jefe de Carrera).
    Route::middleware('rol:secretaria,jefe')->prefix('usuarios')->group(function () {
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

    // HU-07: nómina de estudiantes (Secretaria de Jefatura y Jefe de Carrera)
    Route::middleware('rol:secretaria,jefe')->prefix('nomina')->group(function () {
        Route::get('/', [NominaController::class, 'index']);
        Route::get('/catalogo', [NominaController::class, 'catalogo']);
        Route::get('/plantilla', [NominaController::class, 'plantilla']);
        Route::get('/exportar', [NominaController::class, 'exportar']);
        Route::post('/revisar', [NominaController::class, 'revisar']);
        Route::post('/confirmar', [NominaController::class, 'confirmar']);
        Route::post('/estudiantes', [NominaController::class, 'agregar']);
    });

    // HU-10: documentos de prácticas y acuerdos de colaboración.
    Route::middleware('rol:secretaria,coordinadora,jefe')->group(function () {
        Route::get('/documentos', [DocumentoController::class, 'index']);
        Route::get('/practicas/{practica}/documentos', [DocumentoController::class, 'show']);
        Route::get('/practicas/{practica}/documentos/{tipo}', [DocumentoController::class, 'descargar']);
        Route::post('/practicas/{practica}/documentos/enviar', [DocumentoController::class, 'enviar']);
        Route::post('/centros/{centro}/acuerdo', [DocumentoController::class, 'guardarAcuerdo']);
        Route::post('/centros/{centro}/acuerdo/borrador', [DocumentoController::class, 'borradorAcuerdo']);
        Route::get('/acuerdos/{acuerdo}/archivo', [DocumentoController::class, 'archivoAcuerdo']);
    });

    // HU-12: catálogo de centros; las mutaciones y bajas verifican rol coordinadora o jefe.
    Route::get('/areas', [CentroPracticaController::class, 'areas']);
    Route::post('/areas', [CentroPracticaController::class, 'storeArea']);
    Route::get('/periodos', [CentroPracticaController::class, 'periodos']);
    Route::get('/centros-practica', [CentroPracticaController::class, 'index']);
    Route::get('/centros-practica/{centroPractica}', [CentroPracticaController::class, 'show']);
    Route::post('/centros-practica', [CentroPracticaController::class, 'store']);
    Route::put('/centros-practica/{centroPractica}', [CentroPracticaController::class, 'update']);
    Route::post('/centros-practica/{centroPractica}/dar-de-baja', [CentroPracticaController::class, 'darDeBaja']);
    Route::post('/centros-practica/{centroPractica}/reactivar', [CentroPracticaController::class, 'reactivar']);
    Route::post('/centros-practica/{centroPractica}/supervisores', [CentroPracticaController::class, 'storeSupervisor']);
    Route::post('/centros-practica/{centroPractica}/supervisores/{supervisor}/activar', [CentroPracticaController::class, 'activarSupervisor']);
    Route::post('/centros-practica/{centroPractica}/supervisores/{supervisor}/dar-de-baja', [CentroPracticaController::class, 'darDeBajaSupervisor']);
});
