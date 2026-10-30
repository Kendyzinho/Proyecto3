<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CentroPractica;
use App\Models\Estudiante;
use App\Models\HistorialPractica;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CentrosPracticaTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinadora_puede_agregar_un_area_al_catalogo(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora']);

        $this->actingAs($coordinadora)
            ->postJson('/api/areas', ['nombre' => 'Diseño editorial'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Diseño editorial');

        $this->assertDatabaseHas('areas', ['nombre' => 'Diseño editorial']);
    }

    public function test_coordinadora_crea_centro_con_rut_validado_areas_y_cupos_por_categoria(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora']);
        $area = Area::create(['nombre' => 'Web']);
        $periodo = Periodo::create(['anio' => 2026, 'semestre' => 2]);

        $response = $this->actingAs($coordinadora)->postJson('/api/centros-practica', [
            'rut' => '12.345.678-5',
            'razon_social' => 'Productora de prueba SpA',
            'nombre' => 'Productora de prueba',
            'direccion' => 'Arica',
            'telefono' => '+56 9 1234 5678',
            'es_interno' => false,
            'area_ids' => [$area->id],
            'requerimientos' => [
                ['periodo_id' => $periodo->id, 'categoria' => 'laboral', 'cupos' => 3],
                ['periodo_id' => $periodo->id, 'categoria' => 'profesional', 'cupos' => 1],
            ],
            'supervisores' => [
                ['nombre' => 'Camila Rojas', 'correo' => 'camila.rojas@productora.cl', 'cargo' => 'Directora de arte'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.rut', '12345678-5')
            ->assertJsonPath('data.estado', 'propuesto')
            ->assertJsonPath('data.areas.0.nombre', 'Web')
            ->assertJsonPath('data.requerimientos.0.cupos', 3)
            ->assertJsonPath('data.requerimientos.0.ocupados', 0)
            ->assertJsonPath('data.estado_acuerdo', 'sin_acuerdo');

        $this->assertDatabaseHas('centros_practica', ['rut' => '12345678-5']);
        $this->assertDatabaseHas('requerimientos_centro', [
            'centro_id' => $response->json('data.id'),
            'periodo_id' => $periodo->id,
            'categoria' => 'laboral',
            'cupos' => 3,
        ]);
    }

    public function test_rechaza_rut_invalido_y_usuario_que_no_sea_coordinadora(): void
    {
        $estudiante = User::factory()->create(['rol' => null]);
        $payload = [
            'rut' => '12.345.678-9',
            'razon_social' => 'Centro de prueba',
            'nombre' => 'Centro de prueba',
            'es_interno' => false,
        ];

        $this->actingAs(User::factory()->create(['rol' => 'coordinadora']))
            ->postJson('/api/centros-practica', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rut');

        $this->actingAs($estudiante)
            ->postJson('/api/centros-practica', [...$payload, 'rut' => '12.345.678-5'])
            ->assertForbidden();
    }

    public function test_baja_exige_y_muestra_motivo_en_la_ficha(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora']);
        $centro = $this->crearCentro();

        $this->actingAs($coordinadora)
            ->postJson("/api/centros-practica/{$centro->id}/dar-de-baja", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('motivo_baja');

        $this->postJson("/api/centros-practica/{$centro->id}/dar-de-baja", [
            'motivo_baja' => 'Evaluación desfavorable del centro.',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'dado_de_baja')
            ->assertJsonPath('data.motivo_baja', 'Evaluación desfavorable del centro.');

        $this->getJson("/api/centros-practica/{$centro->id}")
            ->assertOk()
            ->assertJsonPath('data.motivo_baja', 'Evaluación desfavorable del centro.');
    }

    public function test_baja_se_bloquea_por_practica_confirmada_y_no_por_solicitud_pendiente(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora']);
        $centro = $this->crearCentro();
        $practica = $this->crearPractica($centro, $coordinadora, 'confirmada');

        $this->actingAs($coordinadora)
            ->postJson("/api/centros-practica/{$centro->id}/dar-de-baja", ['motivo_baja' => 'Fin de período'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('centro');

        HistorialPractica::where('practica_id', $practica->id)->update(['estado_nuevo' => 'finalizada']);
        $this->postJson("/api/centros-practica/{$centro->id}/dar-de-baja", ['motivo_baja' => 'Fin de período'])
            ->assertOk();

        $centroPendiente = $this->crearCentro('12.345.679-3');
        $this->crearPractica($centroPendiente, $coordinadora, 'por_revisar');
        $this->postJson("/api/centros-practica/{$centroPendiente->id}/dar-de-baja", ['motivo_baja' => 'Centro no elegible'])
            ->assertOk();
    }

    public function test_ficha_separa_estado_del_resultado_y_omite_solicitudes_no_confirmadas(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora']);
        $centro = $this->crearCentro();
        $this->crearPractica($centro, $coordinadora, 'por_revisar');
        $practicaEnCurso = $this->crearPractica($centro, $coordinadora, 'en_curso');

        $this->actingAs($coordinadora)
            ->getJson("/api/centros-practica/{$centro->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.estudiantes')
            ->assertJsonPath('data.estudiantes.0.estado', 'en_curso')
            ->assertJsonPath('data.estudiantes.0.resultado', null);

        DB::table('dictamenes')->insert([
            'practica_id' => $practicaEnCurso->id,
            'coordinadora_id' => $coordinadora->id,
            'resultado' => 'aprobado',
            'fecha' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson("/api/centros-practica/{$centro->id}")
            ->assertOk()
            ->assertJsonPath('data.estudiantes.0.resultado', 'aprobado');
    }

    private function crearCentro(string $rut = '12.345.678-5'): CentroPractica
    {
        return CentroPractica::create([
            'rut' => $rut,
            'razon_social' => 'Centro de prueba',
            'nombre' => 'Centro de prueba',
            'es_interno' => true,
            'fecha_alta' => now()->toDateString(),
        ]);
    }

    private function crearPractica(CentroPractica $centro, User $coordinadora, string $estado): Practica
    {
        $periodo = Periodo::firstOrCreate(['anio' => 2026, 'semestre' => 2]);
        $mallaId = DB::table('mallas')->insertGetId([
            'nombre' => 'Malla de prueba',
            'vigente_desde' => '2018-03-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tipoId = DB::table('tipos_practica')->insertGetId([
            'malla_id' => $mallaId,
            'nombre' => 'Laboral I',
            'categoria' => 'laboral',
            'horas_requeridas' => 64,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $estudianteUser = User::factory()->create(['rol' => null]);
        $estudiante = Estudiante::create(['user_id' => $estudianteUser->id, 'anio_ingreso' => 2024]);
        $habilitacion = Habilitacion::create([
            'estudiante_id' => $estudiante->id,
            'tipo_practica_id' => $tipoId,
            'periodo_id' => $periodo->id,
            'cumple_prerrequisito' => true,
            'horas_requeridas' => 64,
        ]);
        $supervisor = Supervisor::create([
            'centro_id' => $centro->id,
            'nombre' => 'Supervisora de prueba',
            'correo' => 'supervisora@example.test',
            'fecha_alta' => now()->toDateString(),
        ]);
        $practica = Practica::create([
            'habilitacion_id' => $habilitacion->id,
            'supervisor_id' => $supervisor->id,
            'origen' => 'solicitud',
            'modalidad' => 'presencial',
            'fecha_inicio' => now()->addWeek()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(),
        ]);
        HistorialPractica::create([
            'practica_id' => $practica->id,
            'user_id' => $coordinadora->id,
            'fecha_hora' => now(),
            'estado_nuevo' => $estado,
        ]);

        return $practica;
    }
}
