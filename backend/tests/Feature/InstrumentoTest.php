<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PlantillaInstrumento;
use App\Models\Practica;
use App\Models\RespuestaInstrumento;
use App\Models\TipoPractica;
use App\Services\InstrumentoService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\InstrumentoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// Base común de HU-16 (pauta de visita) y HU-19 (informe confidencial)
class InstrumentoTest extends TestCase
{
    use BaseDeDatosAislada;

    private InstrumentoService $service;

    private PlantillaInstrumento $pauta;

    private RespuestaInstrumento $respuesta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class, InstrumentoSeeder::class]);

        $this->service = app(InstrumentoService::class);
        $this->pauta = $this->service->plantillaVigente('pauta', null);
        $practica = Practica::formalizadas()->firstOrFail();
        $this->respuesta = $this->service->crearRespuesta($practica->id, $this->pauta);
    }

    private function informe(string $tipo, string $categoria): PlantillaInstrumento
    {
        $tipoPractica = TipoPractica::where('nombre', $tipo)->where('categoria', $categoria)->orderBy('id')->firstOrFail();

        return $this->service->plantillaVigente('informe', $tipoPractica->id);
    }

    // Respuesta completa de la pauta: 4 en cada criterio y un comentario
    private function completa(): array
    {
        return $this->pauta->items->map(fn (Item $item) => $item->seRespondeConTexto()
            ? ['item_id' => $item->id, 'texto' => 'Buen trabajo en terreno.']
            : ['item_id' => $item->id, 'opcion_id' => $item->opciones->firstWhere('valor_numerico', 4)->id])->all();
    }

    public function test_la_pauta_tiene_seis_criterios_de_1_a_5_y_un_comentario_obligatorio(): void
    {
        $items = $this->pauta->items;

        $this->assertCount(7, $items);
        $this->assertSame(6, $items->where('tipo_respuesta', 'escala')->count());
        $this->assertSame(['1', '2', '3', '4', '5'], $items->first()->opciones->pluck('etiqueta')->all());
        $comentario = $items->firstWhere('clave', 'comentarios');
        $this->assertSame('texto', $comentario->tipo_respuesta);
        $this->assertTrue($comentario->obligatorio);
    }

    public function test_el_informe_cambia_segun_el_tipo_de_practica(): void
    {
        $laboral = $this->informe('Laboral I', 'laboral')->items;
        $profesional = $this->informe('Profesional', 'profesional')->items;

        $this->assertSame(17, $laboral->where('tipo_respuesta', 'escala')->count());
        $this->assertSame(21, $profesional->where('tipo_respuesta', 'escala')->count());
        $this->assertFalse($profesional->firstWhere('clave', 'comentario')->obligatorio);
        $this->assertStringContainsString('320 horas', $profesional->firstWhere('clave', 't10')->enunciado);
        $this->assertSame('Totalmente de acuerdo', $profesional->first()->opciones[4]->etiqueta);
        $this->assertSame('No aplica', $profesional->first()->opciones->last()->etiqueta);
        $this->assertNull($profesional->first()->opciones->last()->valor_numerico);
    }

    public function test_correr_el_seeder_de_nuevo_no_duplica(): void
    {
        $plantillas = PlantillaInstrumento::count();
        $items = Item::count();

        $this->seed(InstrumentoSeeder::class);

        $this->assertSame($plantillas, PlantillaInstrumento::count());
        $this->assertSame($items, Item::count());
    }

    public function test_un_borrador_incompleto_se_guarda_sin_enviar(): void
    {
        $guardada = $this->service->guardar($this->respuesta, array_slice($this->completa(), 0, 2), false);

        $this->assertNull($guardada->fecha_envio);
        $this->assertCount(2, $this->service->leer($guardada)['respuestas']);
    }

    public function test_no_se_envia_incompleto_y_un_comentario_en_blanco_cuenta_como_faltante(): void
    {
        $respuestas = $this->completa();
        $respuestas[6]['texto'] = '   ';

        try {
            $this->service->guardar($this->respuesta, $respuestas, true);
            $this->fail('Debió rechazar el envío incompleto.');
        } catch (ValidationException $e) {
            $this->assertSame([(string) $respuestas[6]['item_id']], $e->errors()['faltantes']);
        }
        $this->assertNull($this->respuesta->fresh()->fecha_envio);
    }

    public function test_enviada_queda_cerrada(): void
    {
        $enviada = $this->service->guardar($this->respuesta, $this->completa(), true);
        $this->assertNotNull($enviada->fecha_envio);

        try {
            $this->service->guardar($enviada, $this->completa(), false);
            $this->fail('Debió rechazar la modificación.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_rechaza_opciones_e_items_que_no_corresponden(): void
    {
        $items = $this->pauta->items;
        $opcionAjena = $items[1]->opciones->first()->id;
        $itemAjeno = $this->informe('Profesional', 'profesional')->items->first()->id;

        foreach ([
            [['item_id' => $items[0]->id, 'opcion_id' => $opcionAjena]],
            [['item_id' => $itemAjeno, 'opcion_id' => $opcionAjena]],
            [['item_id' => $items[6]->id, 'opcion_id' => $opcionAjena]],
            [['item_id' => $items[0]->id, 'texto' => 'Muy bien']],
        ] as $respuestas) {
            try {
                $this->service->guardar($this->respuesta, $respuestas, false);
                $this->fail('Debió rechazar: '.json_encode($respuestas));
            } catch (ValidationException) {
                $this->assertDatabaseCount('detalles_respuesta', 0);
            }
        }
    }

    public function test_un_item_omitido_en_el_borrador_siguiente_se_borra(): void
    {
        $respuestas = $this->completa();
        $this->service->guardar($this->respuesta, $respuestas, false);
        $this->service->guardar($this->respuesta, array_slice($respuestas, 1), false);

        $this->assertDatabaseMissing('detalles_respuesta', ['respuesta_id' => $this->respuesta->id, 'item_id' => $respuestas[0]['item_id']]);
        $this->assertDatabaseCount('detalles_respuesta', 6);
    }

    public function test_crear_respuesta_devuelve_la_misma_si_ya_existe(): void
    {
        $otra = $this->service->crearRespuesta($this->respuesta->practica_id, $this->pauta);

        $this->assertSame($this->respuesta->id, $otra->id);
    }
}
