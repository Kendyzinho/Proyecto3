<?php

namespace Tests\Feature;

use App\Models\{CentroPractica, Estudiante, Habilitacion, HistorialPractica, Periodo, Practica, Supervisor, TipoPractica, User};
use Database\Seeders\CatalogoSeeder;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use BaseDeDatosAislada;

    public function test_no_permite_informar_una_inasistencia_pasadas_24_horas(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 1));
        $this->seed(CatalogoSeeder::class);

        $alumno = User::factory()->create(['rol' => null]);
        $estudiante = Estudiante::create(['user_id' => $alumno->id, 'anio_ingreso' => 2024]);
        $tipo = TipoPractica::where('nombre', 'Laboral I')->firstOrFail();
        $habilitacion = Habilitacion::create([
            'estudiante_id' => $estudiante->id,
            'tipo_practica_id' => $tipo->id,
            'periodo_id' => Periodo::where('anio', 2026)->where('semestre', 2)->firstOrFail()->id,
            'cumple_prerrequisito' => true,
            'horas_requeridas' => 64,
        ]);
        $centro = CentroPractica::create([
            'nombre' => 'Centro de prueba', 'razon_social' => 'Centro de prueba',
            'es_interno' => true, 'fecha_alta' => '2025-01-01',
        ]);
        $supervisor = Supervisor::create([
            'centro_id' => $centro->id, 'nombre' => 'Supervisora de prueba',
            'correo' => 'supervisora@example.test', 'fecha_alta' => '2025-01-01',
        ]);
        $practica = Practica::create([
            'habilitacion_id' => $habilitacion->id, 'supervisor_id' => $supervisor->id,
            'origen' => 'asignacion', 'modalidad' => 'presencial',
            'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-10-31',
        ]);
        HistorialPractica::create([
            'practica_id' => $practica->id,
            'user_id' => User::factory()->create(['rol' => 'coordinadora'])->id,
            'fecha_hora' => now()->subDay(), 'estado_nuevo' => 'en_curso',
        ]);
        $practica->horarios()->create([
            'dia_semana' => 2, 'hora_inicio' => '09:00', 'hora_fin' => '13:00',
            'vigente_desde' => '2026-10-01',
        ]);

        $this->actingAs($alumno)->putJson("/api/estudiante/practicas/{$practica->id}/bitacora/2026-10-20", [
            'tipo' => 'inasistencia',
            'justificacion' => 'Inasistencia informada fuera del plazo.',
            'recuperacion_programada' => '2026-10-22',
        ])->assertUnprocessable()->assertJsonValidationErrors('justificacion');

        $this->assertDatabaseMissing('registros_diarios', [
            'practica_id' => $practica->id,
            'fecha' => '2026-10-20',
        ]);
    }
}
