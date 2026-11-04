<?php

namespace Tests\Feature;

use App\Models\Habilitacion;
use App\Models\Practica;
use App\Models\RequerimientoCentro;
use App\Models\Supervisor;
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

// HU-11 regulariza casos especiales: puede pasarse del cupo, pero solo si la Coordinadora lo confirma.
class CuposExcepcionalTest extends TestCase
{
    use BaseDeDatosAislada;

    private Habilitacion $habilitacion;

    private Supervisor $supervisor;

    private string $url = '/api/coordinacion/practicas-excepcionales';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->actingAs(User::where('email', 'coordinadora@uta.cl')->firstOrFail());
        $this->habilitacion = Habilitacion::whereHas('estudiante.usuario', fn ($q) => $q->where('name', 'like', 'Fernanda%'))->firstOrFail();
        $this->supervisor = Supervisor::where('nombre', 'Paula Contreras')->firstOrFail();
    }

    private function datos(array $extra = []): array
    {
        return $extra + [
            'periodo_id' => $this->habilitacion->periodo_id, 'habilitacion_id' => $this->habilitacion->id,
            'supervisor_id' => $this->supervisor->id, 'modalidad' => 'presencial',
            'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-12-10', 'justificacion_excepcional' => 'Ingreso fuera de plazo.',
        ];
    }

    private function requerimiento(): RequerimientoCentro
    {
        return RequerimientoCentro::where('centro_id', $this->supervisor->centro_id)
            ->where('periodo_id', $this->habilitacion->periodo_id)->where('categoria', 'laboral')->firstOrFail();
    }

    public function test_con_cupo_libre_registra_sin_confirmar(): void
    {
        $this->requerimiento()->update(['cupos' => 50]);

        $this->postJson($this->url, $this->datos())->assertCreated();
    }

    public function test_sin_cupo_avisa_y_no_registra(): void
    {
        $this->requerimiento()->update(['cupos' => 0]);
        $ocupados = Practica::formalizadas()->whereHas('supervisor', fn ($q) => $q->where('centro_id', $this->supervisor->centro_id))
            ->whereHas('habilitacion.tipoPractica', fn ($q) => $q->where('categoria', 'laboral'))->count();

        $this->postJson($this->url, $this->datos())->assertUnprocessable()
            ->assertJsonPath('errors.exceder_cupo.0', 'Este centro no tiene cupos libres de práctica laboral en este período: '.$ocupados.' ocupados de 0.');

        $this->assertFalse($this->habilitacion->practicas()->exists());
    }

    public function test_sin_cupo_registra_si_la_coordinadora_confirma(): void
    {
        $this->requerimiento()->update(['cupos' => 0]);

        $id = $this->postJson($this->url, $this->datos(['exceder_cupo' => true]))->assertCreated()->json('data.id');

        $this->assertTrue(Practica::formalizadas()->whereKey($id)->exists());
    }

    public function test_avisa_si_el_centro_no_tiene_cupos_definidos_en_el_periodo(): void
    {
        $this->requerimiento()->delete();

        $this->postJson($this->url, $this->datos())->assertUnprocessable()
            ->assertJsonPath('errors.exceder_cupo.0', 'Este centro no tiene cupos de práctica laboral definidos en este período.');
    }

    public function test_los_cupos_profesionales_no_se_mezclan_con_los_laborales(): void
    {
        RequerimientoCentro::updateOrCreate(
            ['centro_id' => $this->supervisor->centro_id, 'periodo_id' => $this->habilitacion->periodo_id, 'categoria' => 'profesional'],
            ['cupos' => 0],
        );
        $this->requerimiento()->update(['cupos' => 50]);

        $this->postJson($this->url, $this->datos())->assertCreated();
    }
}
