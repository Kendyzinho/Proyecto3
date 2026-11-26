<?php

namespace Tests\Feature;

use App\Models\HistorialPractica;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\User;
use App\Services\InstrumentoService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\InstrumentoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-15: agenda de visitas técnicas. Datos de DemoSeeder: Camila (Municipalidad), Antonia y Pedro (Productora),
// Josefa (Fundación UTA) y Diego (Corfo) confirmadas; Ignacia por revisar.
class VisitaTecnicaTest extends TestCase
{
    use BaseDeDatosAislada;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class, InstrumentoSeeder::class]);
        $this->actingAs(User::where('email', 'coordinadora@uta.cl')->firstOrFail());
    }

    private function practica(string $estudiante): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', $estudiante))->firstOrFail();
    }

    private function agendar(array $estudiantes, string $fechaHora = '2026-12-02 10:30', string $modalidad = 'terreno')
    {
        return $this->postJson('/api/coordinacion/visitas', [
            'fecha_hora' => $fechaHora,
            'modalidad' => $modalidad,
            'practicas' => array_map(fn ($nombre) => $this->practica($nombre)->id, $estudiantes),
        ]);
    }

    private function sinVisita(): array
    {
        return collect($this->getJson('/api/coordinacion/visitas/centros-sin-visita')->assertOk()->json('data'))
            ->mapWithKeys(fn ($centro) => [$centro['nombre'] => array_column($centro['practicas'], 'estudiante')])
            ->all();
    }

    public function test_sin_visitas_todos_los_centros_con_practicas_salen_en_la_alerta(): void
    {
        $this->assertEquals([
            'Corfo Arica y Parinacota' => ['Diego Choque Vargas'],
            'Fundación UTA' => ['Josefa Ramírez Tapia'],
            'Municipalidad de Arica' => ['Camila Rojas Pizarro'],
            'Productora Norte Visual' => ['Antonia Vega Muñoz', 'Pedro Lagos Rivas'],
        ], $this->sinVisita());
    }

    public function test_el_centro_sale_de_la_alerta_cuando_todas_sus_practicas_tienen_visita(): void
    {
        $this->agendar(['Pedro Lagos Rivas'])->assertCreated();
        $this->assertSame(['Antonia Vega Muñoz'], $this->sinVisita()['Productora Norte Visual']);

        $this->agendar(['Antonia Vega Muñoz'], modalidad: 'virtual')->assertCreated();
        $this->assertArrayNotHasKey('Productora Norte Visual', $this->sinVisita());
    }

    public function test_una_visita_cancelada_no_cuenta(): void
    {
        $id = $this->agendar(['Camila Rojas Pizarro'])->assertCreated()->json('data.id');
        $this->postJson("/api/coordinacion/visitas/{$id}/cancelar")->assertOk();

        $this->assertArrayHasKey('Municipalidad de Arica', $this->sinVisita());
    }

    public function test_valida_centro_estado_y_formato(): void
    {
        $this->agendar(['Camila Rojas Pizarro', 'Pedro Lagos Rivas'])->assertUnprocessable()->assertJsonValidationErrors('practicas');
        $this->agendar(['Ignacia Torres Bravo'])->assertUnprocessable()->assertJsonValidationErrors('practicas');
        $this->agendar(['Camila Rojas Pizarro'], '02/12/2026')->assertUnprocessable()->assertJsonValidationErrors('fecha_hora');
        $this->agendar(['Camila Rojas Pizarro'], modalidad: 'presencial')->assertUnprocessable()->assertJsonValidationErrors('modalidad');
        $this->postJson('/api/coordinacion/visitas', ['fecha_hora' => '2026-12-02 10:30', 'modalidad' => 'terreno', 'practicas' => [999999]])
            ->assertUnprocessable()->assertJsonValidationErrors('practicas.0');
        $this->postJson('/api/coordinacion/visitas', ['fecha_hora' => '2026-12-02 10:30', 'modalidad' => 'terreno', 'practicas' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('practicas');

        // Una práctica confirmada de otro semestre tampoco se puede visitar
        $this->practica('Camila Rojas Pizarro')->habilitacion->update(['periodo_id' => Periodo::where('anio', 2026)->where('semestre', 1)->value('id')]);
        $this->agendar(['Camila Rojas Pizarro'])->assertUnprocessable()->assertJsonValidationErrors('practicas');

        $this->assertDatabaseCount('visitas_tecnicas', 0);
    }

    public function test_lista_las_visitas_del_periodo_con_su_estado(): void
    {
        $this->agendar(['Diego Choque Vargas'], '2026-11-20 09:00')->assertCreated();
        $this->agendar(['Pedro Lagos Rivas', 'Antonia Vega Muñoz'], '2026-12-02 10:30', 'virtual')->assertCreated();

        // Una práctica de otro período no aparece
        $otro = Periodo::where('anio', 2026)->where('semestre', 1)->firstOrFail();
        $this->agendar(['Josefa Ramírez Tapia'])->assertCreated();
        $this->practica('Josefa Ramírez Tapia')->habilitacion->update(['periodo_id' => $otro->id]);

        $visitas = $this->getJson('/api/coordinacion/visitas')->assertOk()->json('data');

        $this->assertCount(2, $visitas);
        $this->assertSame(['2026-11-20 09:00', 'realizada', 'Corfo Arica y Parinacota'], [$visitas[0]['fecha_hora'], $visitas[0]['estado'], $visitas[0]['centro']['nombre']]);
        $this->assertSame(['agendada', 'virtual'], [$visitas[1]['estado'], $visitas[1]['modalidad']]);
        $this->assertSame(['Antonia Vega Muñoz', 'Pedro Lagos Rivas'], array_column($visitas[1]['practicas'], 'estudiante'));
    }

    public function test_practicas_disponibles_solo_trae_las_confirmadas_con_su_centro(): void
    {
        $practicas = collect($this->getJson('/api/coordinacion/visitas/practicas-disponibles')->assertOk()->json('data'));

        $this->assertCount(5, $practicas);
        $this->assertNotContains('Ignacia Torres Bravo', $practicas->pluck('estudiante'));
        $this->assertNotContains('Martín López Olivares', $practicas->pluck('estudiante'));
        $this->assertSame('Productora Norte Visual', $practicas->firstWhere('estudiante', 'Pedro Lagos Rivas')['centro']['nombre']);
    }

    public function test_editar_cambia_fecha_modalidad_y_practicas(): void
    {
        $id = $this->agendar(['Pedro Lagos Rivas'])->json('data.id');

        $this->putJson("/api/coordinacion/visitas/{$id}", [
            'fecha_hora' => '2026-12-03 15:00', 'modalidad' => 'virtual',
            'practicas' => [$this->practica('Pedro Lagos Rivas')->id, $this->practica('Antonia Vega Muñoz')->id],
        ])->assertOk()->assertJsonPath('data.fecha_hora', '2026-12-03 15:00')->assertJsonPath('data.modalidad', 'virtual');

        $this->assertDatabaseCount('visita_practica', 2);
        $this->putJson("/api/coordinacion/visitas/{$id}", [
            'fecha_hora' => '2026-12-03 15:00', 'modalidad' => 'virtual', 'practicas' => [$this->practica('Camila Rojas Pizarro')->id, $this->practica('Pedro Lagos Rivas')->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('practicas');
    }

    public function test_una_practica_ya_incluida_que_finalizo_no_impide_editar(): void
    {
        $pedro = $this->practica('Pedro Lagos Rivas');
        $id = $this->agendar(['Pedro Lagos Rivas'])->json('data.id');
        HistorialPractica::create(['practica_id' => $pedro->id, 'user_id' => auth()->id(), 'fecha_hora' => now(), 'estado_nuevo' => 'finalizada']);

        $this->putJson("/api/coordinacion/visitas/{$id}", ['fecha_hora' => '2026-12-04 09:00', 'modalidad' => 'terreno', 'practicas' => [$pedro->id]])->assertOk();
    }

    public function test_una_visita_cancelada_no_se_edita_ni_se_vuelve_a_cancelar(): void
    {
        $id = $this->agendar(['Camila Rojas Pizarro'])->json('data.id');
        $this->postJson("/api/coordinacion/visitas/{$id}/cancelar")->assertOk()->assertJsonPath('data.estado', 'cancelada');

        $this->postJson("/api/coordinacion/visitas/{$id}/cancelar")->assertUnprocessable();
        $this->putJson("/api/coordinacion/visitas/{$id}", [
            'fecha_hora' => '2026-12-04 09:00', 'modalidad' => 'terreno', 'practicas' => [$this->practica('Camila Rojas Pizarro')->id],
        ])->assertUnprocessable();
    }

    public function test_con_una_pauta_registrada_solo_se_pueden_agregar_practicas(): void
    {
        $pedro = $this->practica('Pedro Lagos Rivas');
        $antonia = $this->practica('Antonia Vega Muñoz');
        $id = $this->agendar(['Pedro Lagos Rivas'], '2026-11-24 10:00')->json('data.id');
        $servicio = app(InstrumentoService::class);
        $servicio->crearRespuesta($pedro->id, $servicio->plantillaVigente('pauta', null), $id);

        $this->postJson("/api/coordinacion/visitas/{$id}/cancelar")->assertUnprocessable();
        $this->putJson("/api/coordinacion/visitas/{$id}", ['fecha_hora' => '2026-11-26 10:00', 'modalidad' => 'terreno', 'practicas' => [$pedro->id]])->assertUnprocessable();
        $this->putJson("/api/coordinacion/visitas/{$id}", ['fecha_hora' => '2026-11-24 10:00', 'modalidad' => 'terreno', 'practicas' => [$antonia->id]])->assertUnprocessable();
        $this->putJson("/api/coordinacion/visitas/{$id}", ['fecha_hora' => '2026-11-24 10:00', 'modalidad' => 'terreno', 'practicas' => [$pedro->id, $this->practica('Camila Rojas Pizarro')->id]])->assertUnprocessable();

        $this->putJson("/api/coordinacion/visitas/{$id}", ['fecha_hora' => '2026-11-24 10:00', 'modalidad' => 'terreno', 'practicas' => [$pedro->id, $antonia->id]])->assertOk();
    }

    public function test_solo_coordinadora_y_jefe_gestionan_visitas(): void
    {
        foreach (['secretaria@uta.cl', 'estudiante@alumnos.uta.cl'] as $correo) {
            $this->actingAs(User::where('email', $correo)->firstOrFail());
            $this->getJson('/api/coordinacion/visitas')->assertForbidden();
            $this->agendar(['Camila Rojas Pizarro'])->assertForbidden();
        }

        $this->actingAs(User::where('email', 'jefe@uta.cl')->firstOrFail());
        $this->agendar(['Camila Rojas Pizarro'])->assertCreated();
        $this->getJson('/api/coordinacion/visitas')->assertOk()->assertJsonCount(1, 'data');
    }
}
