<?php

namespace Tests\Feature;

use App\Models\CentroPractica;
use App\Models\Periodo;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Reunión 4 con el cliente: el Jefe de Carrera ve y hace todo lo de la Coordinadora y la Secretaría
class JefaturaAccesoTest extends TestCase
{
    use RefreshDatabase;

    private User $jefe;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->seed([CatalogoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->jefe = User::where('rol', 'jefe')->firstOrFail();
        $this->periodo = Periodo::where('anio', 2026)->where('semestre', 2)->firstOrFail();
    }

    public function test_el_jefe_entra_a_las_pantallas_de_coordinacion_y_secretaria(): void
    {
        $rutas = [
            '/api/coordinacion/metricas',
            '/api/coordinacion/asignaciones/habilitaciones',
            '/api/coordinacion/calendarios',
            '/api/coordinacion/practicas-excepcionales/catalogo?periodo_id='.$this->periodo->id,
            '/api/solicitudes-practica',
            '/api/centros-practica',
            '/api/usuarios',
            '/api/nomina/catalogo',
            '/api/nomina?periodo_id='.$this->periodo->id,
            '/api/documentos?periodo_id='.$this->periodo->id,
            '/api/mallas',
        ];

        foreach ($rutas as $ruta) {
            $this->actingAs($this->jefe)->getJson($ruta)->assertOk();
        }
    }

    public function test_el_jefe_ve_el_catalogo_completo_de_centros_como_la_coordinadora(): void
    {
        $coordinadora = User::where('rol', 'coordinadora')->firstOrFail();

        $delJefe = $this->actingAs($this->jefe)->getJson('/api/centros-practica')->assertOk()->json('data');
        $deLaCoordinadora = $this->actingAs($coordinadora)->getJson('/api/centros-practica')->assertOk()->json('data');

        $this->assertSame($deLaCoordinadora, $delJefe);
    }

    public function test_el_jefe_puede_dar_de_baja_un_centro_y_crear_una_cuenta(): void
    {
        $centro = CentroPractica::create([
            'rut' => '12.345.678-5', 'razon_social' => 'Centro de prueba', 'nombre' => 'Centro de prueba',
            'es_interno' => true, 'fecha_alta' => now()->toDateString(),
        ]);

        $this->actingAs($this->jefe)
            ->postJson("/api/centros-practica/{$centro->id}/dar-de-baja", ['motivo_baja' => 'Evaluación desfavorable del centro.'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'dado_de_baja');

        $this->actingAs($this->jefe)
            ->postJson('/api/usuarios', ['rol' => 'coordinadora', 'name' => 'Paula Soto', 'rut' => null, 'email' => 'paula.soto@uta.cl'])
            ->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'paula.soto@uta.cl', 'rol' => 'coordinadora']);
    }

    public function test_el_jefe_no_puede_cambiar_su_propio_rol_ni_desactivarse(): void
    {
        $this->actingAs($this->jefe)
            ->putJson("/api/usuarios/{$this->jefe->id}", ['rol' => 'secretaria', 'name' => $this->jefe->name, 'rut' => $this->jefe->rut, 'email' => $this->jefe->email])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rol');

        $this->actingAs($this->jefe)
            ->postJson("/api/usuarios/{$this->jefe->id}/desactivar", ['motivo' => 'Otro motivo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');

        $this->assertSame('jefe', $this->jefe->fresh()->rol);
        $this->assertNull($this->jefe->fresh()->fecha_baja);
    }

    public function test_la_coordinadora_y_la_secretaria_siguen_sin_verse_entre_ellas(): void
    {
        $coordinadora = User::where('rol', 'coordinadora')->firstOrFail();
        $secretaria = User::where('rol', 'secretaria')->firstOrFail();

        $this->actingAs($coordinadora)->getJson('/api/usuarios')->assertForbidden();
        $this->actingAs($coordinadora)->getJson('/api/nomina/catalogo')->assertForbidden();
        $this->actingAs($secretaria)->getJson('/api/coordinacion/metricas')->assertForbidden();
        $this->actingAs($secretaria)->getJson('/api/solicitudes-practica')->assertForbidden();
        $this->actingAs($secretaria)->postJson('/api/coordinacion/asignaciones', [])->assertForbidden();
    }
}
