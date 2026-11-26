<?php

namespace Tests\Feature;

use App\Models\{CentroPractica, Estudiante, Habilitacion, HistorialPractica, Malla, Periodo, Practica, Supervisor, TipoPractica, User};
use Tests\Concerns\BaseDeDatosPracticasTransaccional;
use Tests\TestCase;

class FichaEstudianteTest extends TestCase
{
    use BaseDeDatosPracticasTransaccional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iniciarTransaccionEnPracticas();
    }

    public function test_ficha_sin_bitacora_visitas_ni_dictamen_se_consulta_sin_error(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->setTime(10, 0));
        $alumno = User::factory()->create(['name' => 'Estudiante de prueba', 'rol' => null]);
        $estudiante = Estudiante::create(['user_id' => $alumno->id, 'anio_ingreso' => 2024]);
        $malla = Malla::query()->firstOrCreate(['nombre' => 'Malla test HU-17'], ['vigente_desde' => '2026-03-01']);
        $tipo = TipoPractica::query()->firstOrCreate(
            ['malla_id' => $malla->id, 'nombre' => 'Laboral I'],
            ['categoria' => 'laboral', 'horas_requeridas' => 64, 'prerrequisito' => 'Dato de prueba'],
        );
        $periodo = Periodo::query()->firstOrCreate(['anio' => 2026, 'semestre' => 2]);
        $habilitacion = Habilitacion::create([
            'estudiante_id' => $estudiante->id,
            'tipo_practica_id' => $tipo->id,
            'periodo_id' => $periodo->id,
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
            'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-12-01',
        ]);
        HistorialPractica::create([
            'practica_id' => $practica->id,
            'user_id' => User::factory()->create(['rol' => 'coordinadora'])->id,
            'fecha_hora' => now(), 'estado_nuevo' => 'en_curso',
        ]);

        $coordinadora = User::factory()->create(['name' => 'Coordinadora de prueba', 'rol' => 'coordinadora']);
        $this->actingAs($coordinadora)->getJson("/api/coordinacion/practicas/{$practica->id}/ficha")
            ->assertOk()
            ->assertJsonPath('data.estudiante.nombre', 'Estudiante de prueba')
            ->assertJsonPath('data.bitacora.horas_confirmadas', 0)
            ->assertJsonPath('data.bitacora.porcentaje_avance', 0)
            ->assertJsonPath('data.visitas', [])
            ->assertJsonPath('data.evaluacion.estado', 'Pendiente')
            ->assertJsonPath('data.documentos.0.registrado', false);

        $this->actingAs($alumno)->getJson("/api/coordinacion/practicas/{$practica->id}/ficha")->assertForbidden();
    }
}
