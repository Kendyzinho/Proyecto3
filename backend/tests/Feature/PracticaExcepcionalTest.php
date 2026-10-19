<?php

namespace Tests\Feature;

use App\Models\CentroPractica;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\TipoPractica;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

class PracticaExcepcionalTest extends TestCase
{
    use BaseDeDatosAislada;

    private User $coordinadora;

    private Habilitacion $habilitacion;

    private Supervisor $supervisor;

    private array $datos;

    private string $url = '/api/coordinacion/practicas-excepcionales';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 18));
        $this->seed(CatalogoSeeder::class);
        $this->coordinadora = User::factory()->create(['rol' => 'coordinadora']);
        $usuario = User::factory()->create(['rol' => null]);
        $estudiante = Estudiante::create(['user_id' => $usuario->id, 'anio_ingreso' => 2024]);
        $this->habilitacion = Habilitacion::create(['estudiante_id' => $estudiante->id, 'tipo_practica_id' => TipoPractica::where('nombre', 'Laboral I')->firstOrFail()->id,
            'periodo_id' => Periodo::actual()->id, 'cumple_prerrequisito' => true, 'horas_requeridas' => 64]);
        $centro = CentroPractica::create(['nombre' => 'Centro de prueba UTA', 'razon_social' => 'UTA', 'es_interno' => true, 'fecha_alta' => '2025-01-01']);
        $this->supervisor = Supervisor::create(['centro_id' => $centro->id, 'nombre' => 'Supervisora de prueba', 'correo' => 'supervisora@example.test', 'fecha_alta' => '2025-01-01']);
        $this->datos = ['periodo_id' => $this->habilitacion->periodo_id, 'habilitacion_id' => $this->habilitacion->id,
            'supervisor_id' => $this->supervisor->id, 'modalidad' => 'presencial', 'fecha_inicio' => '2026-11-01',
            'fecha_fin' => '2026-12-01', 'justificacion_excepcional' => 'Regularización autorizada por Jefatura.'];
    }

    public function test_exige_autenticacion_en_todos_los_endpoints(): void
    {
        $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertUnauthorized();
        $this->getJson('/api/coordinacion/metricas')->assertUnauthorized();
        $this->postJson($this->url, $this->datos)->assertUnauthorized();
    }

    public function test_rechaza_otros_roles(): void
    {
        foreach (['secretaria', 'jefe', null] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]));
            $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertForbidden();
            $this->getJson('/api/coordinacion/metricas')->assertForbidden();
            $this->postJson($this->url, $this->datos)->assertForbidden();
        }
    }

    public function test_catalogo_filtra_habilitaciones_y_supervisores(): void
    {
        $this->actingAs($this->coordinadora)->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertOk()
            ->assertJsonCount(1, 'habilitaciones')->assertJsonCount(1, 'supervisores')
            ->assertJsonPath('supervisores.0.centro', 'Centro de prueba UTA');
        $this->habilitacion->update(['cumple_prerrequisito' => false]);
        $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertJsonCount(0, 'habilitaciones');
        $this->habilitacion->update(['cumple_prerrequisito' => true]);
        $this->habilitacion->estudiante->usuario->update(['fecha_baja' => now()]);
        $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertJsonCount(0, 'habilitaciones');
        $this->supervisor->update(['fecha_baja' => now()]);
        $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertJsonCount(0, 'supervisores');
    }

    public function test_crea_con_autoria_origen_y_fecha_y_actualiza_metrica(): void
    {
        $this->actingAs($this->coordinadora)->getJson('/api/coordinacion/metricas')->assertJsonPath('practicas_registradas', 0);
        $id = $this->postJson($this->url, $this->datos)->assertCreated()->assertJsonPath('data.origen', 'excepcional')->json('data.id');
        $this->assertDatabaseHas('practicas', ['id' => $id, 'registrada_por' => $this->coordinadora->id,
            'justificacion_excepcional' => $this->datos['justificacion_excepcional'], 'supervisor_id' => $this->supervisor->id]);
        $this->assertNotNull(Practica::findOrFail($id)->created_at);
        $this->getJson('/api/coordinacion/metricas')->assertJsonPath('practicas_registradas', 1);
        $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertJsonCount(0, 'habilitaciones');
        // La nueva práctica participa inmediatamente en el flujo real de HU-10.
        $this->getJson('/api/documentos?periodo_id='.$this->habilitacion->periodo_id)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->actingAs(User::factory()->create(['rol' => 'secretaria']))->getJson('/api/nomina?periodo_id='.$this->habilitacion->periodo_id)
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_acepta_modalidades_presencial_y_mixta(): void
    {
        $this->actingAs($this->coordinadora);
        $tipo = TipoPractica::where('nombre', 'Laboral II')->firstOrFail();

        foreach (['presencial', 'mixta'] as $modalidad) {
            $usuario = User::factory()->create(['rol' => null]);
            $estudiante = Estudiante::create(['user_id' => $usuario->id, 'anio_ingreso' => 2024]);
            $habilitacion = Habilitacion::create([
                'estudiante_id' => $estudiante->id,
                'tipo_practica_id' => $tipo->id,
                'periodo_id' => Periodo::actual()->id,
                'cumple_prerrequisito' => true,
                'horas_requeridas' => $tipo->horas_requeridas,
            ]);

            $id = $this->postJson($this->url, array_replace($this->datos, [
                'habilitacion_id' => $habilitacion->id,
                'modalidad' => $modalidad,
            ]))->assertCreated()->json('data.id');
            $this->assertDatabaseHas('practicas', ['id' => $id, 'modalidad' => $modalidad]);
        }

        $this->assertDatabaseCount('practicas', 2);
    }

    public function test_rechaza_a_distancia_en_el_registro_excepcional(): void
    {
        $this->actingAs($this->coordinadora)
            ->postJson($this->url, array_replace($this->datos, ['modalidad' => 'a_distancia']))
            ->assertUnprocessable()->assertJsonValidationErrors('modalidad');

        $this->assertDatabaseCount('practicas', 0);
    }

    public function test_rechaza_duplicados_aunque_la_practica_previa_haya_terminado(): void
    {
        $this->actingAs($this->coordinadora)->postJson($this->url, $this->datos)->assertCreated();
        Practica::first()->update(['fecha_fin' => '2026-09-01']);
        $this->postJson($this->url, $this->datos)->assertUnprocessable()->assertJsonValidationErrors('habilitacion_id');
        $this->assertDatabaseCount('practicas', 1);
    }

    public function test_valida_campos_y_no_acepta_identidad_centro_u_origen_del_cliente(): void
    {
        $this->actingAs($this->coordinadora);
        foreach ([
            ['justificacion_excepcional', ''], ['justificacion_excepcional', '   '], ['justificacion_excepcional', str_repeat('a', 5001)],
            ['modalidad', 'remota'], ['fecha_inicio', '2026-02-30'], ['fecha_inicio', ''],
            ['fecha_fin', '2026-10-01'], ['fecha_fin', ''], ['periodo_id', Periodo::where('semestre', 1)->firstOrFail()->id],
            ['habilitacion_id', 99999], ['supervisor_id', 99999],
            ['centro_id', 999], ['origen', 'solicitud'], ['registrada_por', 999],
        ] as [$campo, $valor]) {
            $this->postJson($this->url, array_replace($this->datos, [$campo => $valor]))->assertUnprocessable()
                ->assertJsonValidationErrors($campo === 'periodo_id' ? 'habilitacion_id' : $campo);
        }
        $this->assertDatabaseCount('practicas', 0);
    }

    public function test_revalida_prerrequisito_y_baja_del_estudiante(): void
    {
        $this->actingAs($this->coordinadora);
        $this->habilitacion->update(['cumple_prerrequisito' => false]);
        $this->postJson($this->url, $this->datos)->assertUnprocessable()->assertJsonValidationErrors('habilitacion_id');
        $this->habilitacion->update(['cumple_prerrequisito' => true]);
        $this->habilitacion->estudiante->usuario->update(['fecha_baja' => now()]);
        $this->postJson($this->url, $this->datos)->assertUnprocessable()->assertJsonValidationErrors('habilitacion_id');
    }

    public function test_revalida_supervisor_centro_y_acuerdo(): void
    {
        $this->actingAs($this->coordinadora);
        $this->supervisor->update(['fecha_baja' => now()]);
        $this->postJson($this->url, $this->datos)->assertUnprocessable()->assertJsonValidationErrors('supervisor_id');
        $this->supervisor->update(['fecha_baja' => null]);
        $centro = $this->supervisor->centro;
        $centro->update(['fecha_baja' => now()]);
        $this->postJson($this->url, $this->datos)->assertUnprocessable()->assertJsonValidationErrors('supervisor_id');
        $centro->update(['fecha_baja' => null, 'es_interno' => false]);
        $this->postJson($this->url, $this->datos)->assertUnprocessable()->assertJsonValidationErrors('supervisor_id');
        $this->getJson($this->url.'/catalogo?periodo_id='.$this->habilitacion->periodo_id)->assertJsonCount(0, 'supervisores');
        $centro->acuerdos()->create(['tipo' => 'tradicional', 'representante_legal' => 'Representante', 'fecha_inicio' => '2026-01-01', 'fecha_termino' => '2026-12-31']);
        $this->postJson($this->url, $this->datos)->assertCreated();
    }

    public function test_metrica_solo_cuenta_el_periodo_actual(): void
    {
        $this->habilitacion->update(['periodo_id' => Periodo::where('semestre', 1)->firstOrFail()->id]);
        $this->actingAs($this->coordinadora)->postJson($this->url, array_replace($this->datos, ['periodo_id' => Periodo::where('semestre', 1)->firstOrFail()->id]))->assertCreated();
        $this->getJson('/api/coordinacion/metricas')->assertOk()->assertJsonPath('periodo.id', Periodo::actual()->id)->assertJsonPath('practicas_registradas', 0);
    }

    public function test_dos_peticiones_concurrentes_crean_solo_una_practica(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('Se requieren procesos PHP independientes.');
        }
        $token = $this->coordinadora->createToken('concurrencia')->plainTextToken;
        $db = $this->app['db']->connection();
        // Los procesos deben ver los fixtures: solo se confirma dentro de practicas_test,
        // ya validada por BaseDeDatosAislada. El siguiente test reconstruye esa base.
        $db->commit();
        RefreshDatabaseState::$migrated = false;
        $procesos = [];
        try {
            $db->beginTransaction();
            Habilitacion::whereKey($this->habilitacion->id)->lockForUpdate()->firstOrFail();
            foreach ([1, 2] as $n) {
                $proceso = new Process([PHP_BINARY, base_path('tests/Support/registro-excepcional-worker.php')], base_path());
                $proceso->setInput(json_encode(['token' => $token, 'datos' => $this->datos]));
                $proceso->setTimeout(20);
                $proceso->start();
                $procesos[] = $proceso;
                $this->assertTrue($proceso->waitUntil(fn ($tipo, $salida) => str_contains($salida, 'READY')));
            }
            $db->commit();
            $estados = [];
            foreach ($procesos as $proceso) {
                $proceso->wait();
                $this->assertTrue($proceso->isSuccessful(), $proceso->getErrorOutput());
                $estados[] = (int) trim(str_replace('READY', '', $proceso->getOutput()));
            }
            sort($estados);
            $this->assertSame([201, 422], $estados);
            $this->assertSame(1, Practica::where('habilitacion_id', $this->habilitacion->id)->count());
        } finally {
            foreach ($procesos as $proceso) {
                if ($proceso->isRunning()) {
                    $proceso->stop();
                }
            }
            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            $db->beginTransaction();
        }
    }
}
