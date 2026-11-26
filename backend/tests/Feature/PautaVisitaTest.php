<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PlantillaInstrumento;
use App\Models\Practica;
use App\Models\User;
use App\Models\VisitaTecnica;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\InstrumentoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-16: pauta de la visita. Visita realizada el 24-11 a Pedro y Antonia (Productora Norte Visual).
class PautaVisitaTest extends TestCase
{
    use BaseDeDatosAislada;

    private VisitaTecnica $visita;

    private Practica $pedro;

    private Practica $antonia;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class, InstrumentoSeeder::class]);
        $coordinadora = User::where('email', 'coordinadora@uta.cl')->firstOrFail();
        $this->actingAs($coordinadora);

        $this->pedro = $this->practica('Pedro Lagos Rivas');
        $this->antonia = $this->practica('Antonia Vega Muñoz');
        $this->visita = $this->visita('2026-11-24 15:00', [$this->pedro, $this->antonia]);
    }

    private function practica(string $estudiante): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', $estudiante))->firstOrFail();
    }

    private function visita(string $fechaHora, array $practicas, bool $cancelada = false): VisitaTecnica
    {
        $visita = VisitaTecnica::create([
            'coordinadora_id' => User::where('email', 'coordinadora@uta.cl')->value('id'),
            'fecha_hora' => $fechaHora, 'modalidad' => 'terreno', 'cancelada' => $cancelada,
        ]);
        $visita->practicas()->sync(array_map(fn (Practica $practica) => $practica->id, $practicas));

        return $visita;
    }

    private function items(): array
    {
        $plantilla = PlantillaInstrumento::where('instrumento', 'pauta')->firstOrFail();

        return Item::with('opciones')->where('plantilla_id', $plantilla->id)->orderBy('orden')->get()->keyBy('clave')->all();
    }

    // Los 6 criterios con nota $nota y, si se pide, el comentario
    private function respuestas(int $nota = 4, ?string $comentario = 'Buen ambiente y tareas acordes.', ?int $soloCriterios = null): array
    {
        $filas = [];
        foreach ($this->items() as $clave => $item) {
            if ($clave === 'comentarios') {
                if ($comentario !== null) {
                    $filas[] = ['item_id' => $item->id, 'texto' => $comentario];
                }

                continue;
            }
            if ($soloCriterios !== null && count($filas) >= $soloCriterios) {
                continue;
            }
            $filas[] = ['item_id' => $item->id, 'opcion_id' => $item->opciones->firstWhere('valor_numerico', $nota)->id];
        }

        return $filas;
    }

    private function guardar(Practica $practica, array $respuestas, bool $enviar, ?VisitaTecnica $visita = null)
    {
        $visita ??= $this->visita;

        return $this->putJson("/api/coordinacion/visitas/{$visita->id}/pautas/{$practica->id}", [
            'respuestas' => $respuestas, 'enviar' => $enviar,
        ]);
    }

    private function resumen(): array
    {
        $visita = collect($this->getJson('/api/coordinacion/visitas')->assertOk()->json('data'))->firstWhere('id', $this->visita->id);

        return $visita['pautas'];
    }

    public function test_trae_el_formulario_y_las_practicas_sin_respuesta(): void
    {
        $datos = $this->getJson("/api/coordinacion/visitas/{$this->visita->id}/pautas")->assertOk()->json('data');

        $this->assertSame($this->visita->id, $datos['visita']['id']);
        $this->assertCount(7, $datos['formulario']['items']);
        $this->assertSame(['1', '2', '3', '4', '5'], array_column($datos['formulario']['items'][0]['opciones'], 'etiqueta'));
        $this->assertSame(['Antonia Vega Muñoz', 'Pedro Lagos Rivas'], array_column($datos['practicas'], 'estudiante'));
        $this->assertNull($datos['practicas'][0]['respuesta']);
        $this->assertSame(['enviadas' => 0, 'registradas' => 0, 'total' => 2], $this->resumen());
    }

    public function test_un_borrador_incompleto_se_guarda_y_se_vuelve_a_leer(): void
    {
        $this->guardar($this->pedro, $this->respuestas(comentario: null, soloCriterios: 2), enviar: false)->assertOk();

        $pedro = collect($this->getJson("/api/coordinacion/visitas/{$this->visita->id}/pautas")->json('data.practicas'))
            ->firstWhere('estudiante', 'Pedro Lagos Rivas');
        $this->assertCount(2, $pedro['respuesta']['respuestas']);
        $this->assertNull($pedro['respuesta']['enviada_en']);
        $this->assertSame(['enviadas' => 0, 'registradas' => 1, 'total' => 2], $this->resumen());
    }

    public function test_enviar_exige_todo_y_despues_cuenta_como_enviada(): void
    {
        $this->guardar($this->pedro, $this->respuestas(comentario: null), enviar: true)
            ->assertStatus(422)
            ->assertJsonValidationErrors('respuestas')
            ->assertJsonPath('errors.faltantes.0', (string) $this->items()['comentarios']->id);

        $this->guardar($this->pedro, $this->respuestas(), enviar: true)->assertOk()->assertJsonPath('data.enviada_en', fn ($fecha) => $fecha !== null);
        $this->assertSame(['enviadas' => 1, 'registradas' => 1, 'total' => 2], $this->resumen());
    }

    public function test_una_pauta_enviada_no_se_modifica(): void
    {
        $this->guardar($this->pedro, $this->respuestas(), enviar: true)->assertOk();

        $this->guardar($this->pedro, $this->respuestas(nota: 2), enviar: false)->assertStatus(409);
    }

    public function test_no_se_registra_pauta_de_una_visita_futura_ni_cancelada(): void
    {
        $futura = $this->visita('2026-12-02 10:30', [$this->pedro]);
        $this->guardar($this->pedro, $this->respuestas(), enviar: false, visita: $futura)
            ->assertStatus(422)->assertJsonValidationErrors('visita');

        $cancelada = $this->visita('2026-11-20 10:30', [$this->pedro], cancelada: true);
        $this->guardar($this->pedro, $this->respuestas(), enviar: false, visita: $cancelada)
            ->assertStatus(422)->assertJsonValidationErrors('visita');
    }

    public function test_una_practica_que_no_esta_en_la_visita_da_404(): void
    {
        $this->guardar($this->practica('Camila Rojas Pizarro'), $this->respuestas(), enviar: false)->assertNotFound();
    }

    public function test_un_formato_malo_da_422_y_nunca_500(): void
    {
        $url = "/api/coordinacion/visitas/{$this->visita->id}/pautas/{$this->pedro->id}";
        $comentario = $this->items()['comentarios']->id;

        $this->putJson($url, ['respuestas' => 'hola', 'enviar' => false])->assertStatus(422)->assertJsonValidationErrors('respuestas');
        $this->putJson($url, ['respuestas' => [['item_id' => $comentario, 'texto' => ['a']]], 'enviar' => false])
            ->assertStatus(422)->assertJsonValidationErrors('respuestas.0.texto');
        $this->putJson($url, ['respuestas' => []])->assertStatus(422)->assertJsonValidationErrors('enviar');
        // Una opción de otro criterio
        $items = $this->items();
        $this->putJson($url, ['respuestas' => [['item_id' => $items['condiciones']->id, 'opcion_id' => $items['pertinencia']->opciones->first()->id]], 'enviar' => false])
            ->assertStatus(422)->assertJsonValidationErrors('respuestas.0.opcion_id');
    }

    public function test_las_pautas_de_una_practica_solo_traen_las_enviadas(): void
    {
        $this->guardar($this->antonia, $this->respuestas(soloCriterios: 1), enviar: false)->assertOk();
        $this->guardar($this->pedro, $this->respuestas(), enviar: true)->assertOk();

        $this->getJson("/api/coordinacion/practicas/{$this->antonia->id}/pautas")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/coordinacion/practicas/{$this->pedro->id}/pautas")
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.visita.fecha_hora', '2026-11-24 15:00')
            ->assertJsonPath('data.0.visita.modalidad', 'terreno');
    }

    public function test_con_pauta_registrada_la_visita_no_se_cancela(): void
    {
        $this->guardar($this->pedro, $this->respuestas(soloCriterios: 1), enviar: false)->assertOk();

        $this->postJson("/api/coordinacion/visitas/{$this->visita->id}/cancelar")->assertStatus(422)->assertJsonValidationErrors('visita');
    }

    public function test_solo_coordinadora_y_jefe_registran_pautas(): void
    {
        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail());
        $this->getJson("/api/coordinacion/visitas/{$this->visita->id}/pautas")->assertForbidden();

        $this->actingAs(User::where('email', 'jefe@uta.cl')->firstOrFail());
        $this->getJson("/api/coordinacion/visitas/{$this->visita->id}/pautas")->assertOk();
        $this->guardar($this->pedro, $this->respuestas(), enviar: true)->assertOk();
    }
}
