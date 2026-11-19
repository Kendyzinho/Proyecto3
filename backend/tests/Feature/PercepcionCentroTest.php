<?php

namespace Tests\Feature;

use App\Models\{CentroPractica, Estudiante, Habilitacion, HistorialPractica, Periodo, Practica, Supervisor, TipoPractica, User};
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\PercepcionCentroSeeder;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

class PercepcionCentroTest extends TestCase
{
    use BaseDeDatosAislada;

    public function test_estudiante_consulta_guarda_y_actualiza_solo_su_practica(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 18));
        $this->seed(CatalogoSeeder::class);
        $this->seed(PercepcionCentroSeeder::class);
        $alumno = User::factory()->create(['rol' => null]);
        $estudiante = Estudiante::create(['user_id' => $alumno->id, 'anio_ingreso' => 2024]);
        $habilitacion = Habilitacion::create(['estudiante_id' => $estudiante->id, 'tipo_practica_id' => TipoPractica::firstOrFail()->id,
            'periodo_id' => Periodo::actual()->id, 'cumple_prerrequisito' => true, 'horas_requeridas' => 64]);
        $centro = CentroPractica::create(['nombre' => 'Centro prueba', 'razon_social' => 'Centro prueba', 'es_interno' => true, 'fecha_alta' => '2025-01-01']);
        $supervisor = Supervisor::create(['centro_id' => $centro->id, 'nombre' => 'Supervisora', 'correo' => 'supervisora@example.test', 'fecha_alta' => '2025-01-01']);
        $practica = Practica::create(['habilitacion_id' => $habilitacion->id, 'supervisor_id' => $supervisor->id, 'origen' => 'asignacion', 'modalidad' => 'presencial']);
        HistorialPractica::create(['practica_id' => $practica->id, 'user_id' => User::factory()->create(['rol' => 'coordinadora'])->id,
            'fecha_hora' => now(), 'estado_nuevo' => 'confirmada']);
        $url = "/api/estudiante/practicas/{$practica->id}/percepcion-centro";
        $datos = ['clima_laboral' => 4, 'pago_viatico' => 3, 'pertinencia_tareas' => 5, 'observaciones' => 'Buen acompañamiento.'];

        $this->getJson('/api/estudiante/percepciones-centro')->assertUnauthorized();
        $this->actingAs($alumno)->getJson('/api/estudiante/percepciones-centro')->assertOk()->assertJsonCount(1, 'data');
        $this->assertFalse($practica->cumpleRequisitoPercepcionParaFinalizar());
        $this->putJson($url, ['clima_laboral' => 6])->assertUnprocessable();
        $this->putJson($url, [...$datos, 'pago_viatico' => null])->assertUnprocessable();
        $this->putJson($url, $datos)->assertOk()->assertJsonPath('data.clima_laboral', 4);
        $this->assertTrue($practica->cumpleRequisitoPercepcionParaFinalizar());
        $this->putJson($url, [...$datos, 'clima_laboral' => 5])->assertOk();
        $this->assertDatabaseCount('respuestas_instrumento', 1);
        $this->assertDatabaseCount('detalles_respuesta', 4);
        $this->getJson('/api/estudiante/percepciones-centro')->assertJsonPath('data.0.percepcion.clima_laboral', 5);
        $otro = User::factory()->create(['rol' => null]);
        Estudiante::create(['user_id' => $otro->id, 'anio_ingreso' => 2024]);
        $this->actingAs($otro)->getJson('/api/estudiante/percepciones-centro')->assertJsonCount(0, 'data');
        $this->putJson($url, $datos)->assertNotFound();
        $this->actingAs(User::factory()->create(['rol' => 'secretaria']))
            ->getJson('/api/estudiante/percepciones-centro')->assertForbidden();
    }
}
