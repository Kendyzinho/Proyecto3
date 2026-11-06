<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Habilitacion;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-08: sin áreas un estudiante no se puede asignar, así que la Coordinadora las corrige desde la asignación.
class AreasEstudianteTest extends TestCase
{
    use BaseDeDatosAislada;

    private Habilitacion $habilitacion;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->actingAs(User::where('email', 'coordinadora@uta.cl')->firstOrFail());
        $this->habilitacion = Habilitacion::whereHas('estudiante.usuario', fn ($q) => $q->where('name', 'like', 'Fernanda%'))->firstOrFail();
        $this->url = "/api/coordinacion/asignaciones/habilitaciones/{$this->habilitacion->id}/areas";
    }

    private function centros(): array
    {
        return $this->getJson("/api/coordinacion/asignaciones/habilitaciones/{$this->habilitacion->id}/centros")->assertOk()->json('data');
    }

    public function test_un_estudiante_sin_areas_queda_asignable_al_definirlas(): void
    {
        $this->habilitacion->estudiante->areas()->detach();
        $this->assertSame([], $this->centros());
        $area = Area::where('nombre', 'Diseño gráfico y diagramación')->firstOrFail();

        $this->putJson($this->url, ['area_ids' => [$area->id]])->assertOk()
            ->assertJsonPath('data.areas', ['Diseño gráfico y diagramación']);

        $centro = $this->centros()[0];
        $this->postJson('/api/coordinacion/asignaciones', [
            'habilitacion_id' => $this->habilitacion->id, 'centro_id' => $centro['id'],
            'supervisor_id' => $centro['supervisores'][0]['id'], 'modalidad' => 'presencial',
        ])->assertCreated();
    }

    public function test_reemplaza_las_areas_anteriores(): void
    {
        $area = Area::where('nombre', 'Fotografía')->firstOrFail();

        $this->putJson($this->url, ['area_ids' => [$area->id]])->assertOk();

        $this->assertSame(['Fotografía'], $this->habilitacion->estudiante->areas()->pluck('nombre')->all());
    }

    public function test_exige_al_menos_un_area_activa(): void
    {
        $area = Area::where('nombre', 'Fotografía')->firstOrFail();
        $antes = $this->habilitacion->estudiante->areas()->count();

        $this->putJson($this->url, ['area_ids' => []])->assertJsonValidationErrors('area_ids');
        $this->putJson($this->url, ['area_ids' => [999999]])->assertJsonValidationErrors('area_ids.0');
        $area->update(['fecha_baja' => now()]);
        $this->putJson($this->url, ['area_ids' => [$area->id]])->assertJsonValidationErrors('area_ids.0');

        $this->assertSame($antes, $this->habilitacion->estudiante->areas()->count());
    }

    public function test_solo_la_coordinadora_edita_las_areas(): void
    {
        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail());

        $this->putJson($this->url, ['area_ids' => [Area::firstOrFail()->id]])->assertForbidden();
    }
}
