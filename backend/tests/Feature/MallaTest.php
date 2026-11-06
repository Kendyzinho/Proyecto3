<?php

namespace Tests\Feature;

use App\Models\Malla;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// HU-06: mallas de 2 o 3 prácticas con sus horas, configurables por el Jefe de Carrera
class MallaTest extends TestCase
{
    use RefreshDatabase;

    private User $jefe;

    private Malla $actual;

    private Malla $nueva;

    protected function setUp(): void
    {
        parent::setUp();
        // "Malla actual" vigente con 3 prácticas y "Malla nueva" en preparación con 2
        $this->seed(CatalogoSeeder::class);
        $this->jefe = User::factory()->create(['rol' => 'jefe']);
        $this->actual = Malla::where('nombre', 'Malla actual')->firstOrFail();
        $this->nueva = Malla::where('nombre', 'Malla nueva')->firstOrFail();
    }

    private function datosPractica(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Laboral II', 'categoria' => 'laboral', 'horas_requeridas' => 96, 'prerrequisito' => 'Laboral aprobada',
        ], $cambios);
    }

    public function test_lista_la_malla_vigente_y_la_que_esta_en_preparacion(): void
    {
        $this->actingAs($this->jefe)
            ->getJson('/api/mallas')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Malla actual')
            ->assertJsonPath('data.0.estado', 'vigente')
            ->assertJsonCount(3, 'data.0.practicas')
            ->assertJsonPath('data.0.practicas.2.horas_requeridas', 320)
            ->assertJsonPath('data.1.estado', 'en_preparacion')
            ->assertJsonCount(2, 'data.1.practicas');
    }

    public function test_agrega_una_practica_a_la_malla_en_preparacion(): void
    {
        $this->actingAs($this->jefe)
            ->postJson("/api/mallas/{$this->nueva->id}/practicas", $this->datosPractica(['nombre' => 'Laboral  II']))
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Laboral II')
            ->assertJsonPath('data.horas_requeridas', 96);

        $this->assertSame(3, $this->nueva->tiposPractica()->count());
    }

    public function test_no_agrega_practicas_a_la_malla_vigente(): void
    {
        $this->actingAs($this->jefe)
            ->postJson("/api/mallas/{$this->actual->id}/practicas", $this->datosPractica(['nombre' => 'Laboral III']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_no_repite_el_nombre_de_una_practica_en_la_misma_malla(): void
    {
        $this->actingAs($this->jefe)
            ->postJson("/api/mallas/{$this->nueva->id}/practicas", $this->datosPractica(['nombre' => 'Profesional']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_edita_las_horas_de_una_practica_de_la_malla_vigente(): void
    {
        $laboral = $this->actual->tiposPractica()->where('nombre', 'Laboral I')->firstOrFail();

        $this->actingAs($this->jefe)
            ->putJson("/api/tipos-practica/{$laboral->id}", [
                'nombre' => 'Laboral I', 'categoria' => 'laboral', 'horas_requeridas' => 96, 'prerrequisito' => 'Primer año aprobado',
            ])
            ->assertOk()
            ->assertJsonPath('data.horas_requeridas', 96);
    }

    public function test_quita_una_practica_de_la_malla_en_preparacion(): void
    {
        $laboral = $this->nueva->tiposPractica()->where('nombre', 'Laboral')->firstOrFail();

        $this->actingAs($this->jefe)->deleteJson("/api/tipos-practica/{$laboral->id}")->assertNoContent();

        $this->assertDatabaseMissing('tipos_practica', ['id' => $laboral->id]);
    }

    public function test_no_quita_practicas_de_la_malla_vigente(): void
    {
        $profesional = $this->actual->tiposPractica()->where('nombre', 'Profesional')->firstOrFail();

        $this->actingAs($this->jefe)
            ->deleteJson("/api/tipos-practica/{$profesional->id}")
            ->assertStatus(422);
    }

    public function test_publica_la_malla_con_su_fecha_de_vigencia(): void
    {
        $fecha = now()->addMonth()->toDateString();

        $this->actingAs($this->jefe)
            ->postJson("/api/mallas/{$this->nueva->id}/publicar", ['vigente_desde' => $fecha])
            ->assertOk()
            ->assertJsonPath('data.estado', 'programada')
            ->assertJsonPath('data.vigente_desde', $fecha);

        // Hasta esa fecha sigue rigiendo la malla actual
        $this->assertTrue(Malla::vigente()->is($this->actual));
    }

    public function test_no_publica_con_una_fecha_pasada(): void
    {
        $this->actingAs($this->jefe)
            ->postJson("/api/mallas/{$this->nueva->id}/publicar", ['vigente_desde' => now()->subDay()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vigente_desde');
    }

    public function test_no_publica_una_malla_sin_practicas(): void
    {
        $this->nueva->tiposPractica()->delete();

        $this->actingAs($this->jefe)
            ->postJson("/api/mallas/{$this->nueva->id}/publicar", ['vigente_desde' => now()->addMonth()->toDateString()])
            ->assertStatus(422)
            ->assertJsonValidationErrors('vigente_desde');
    }

    public function test_no_crea_otra_malla_mientras_haya_una_en_preparacion(): void
    {
        $this->actingAs($this->jefe)
            ->postJson('/api/mallas', ['nombre' => 'Malla 2028'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_crea_una_malla_en_preparacion_y_sin_practicas(): void
    {
        $this->nueva->update(['vigente_desde' => now()->addMonth()->toDateString()]);

        $this->actingAs($this->jefe)
            ->postJson('/api/mallas', ['nombre' => 'Malla 2028'])
            ->assertCreated()
            ->assertJsonPath('data.estado', 'en_preparacion')
            ->assertJsonCount(0, 'data.practicas');
    }

    public function test_otros_roles_no_pueden_configurar_mallas(): void
    {
        $secretaria = User::factory()->create(['rol' => 'secretaria']);

        $this->actingAs($secretaria)->getJson('/api/mallas')->assertForbidden();
    }
}
