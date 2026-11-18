<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\HistorialPractica;
use App\Models\Practica;
use App\Models\TipoPractica;
use App\Models\User;
use App\Services\ListadoPracticasService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

class ListadoPracticasTest extends TestCase
{
    use BaseDeDatosAislada;

    private User $coordinadora;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->coordinadora = User::where('email', 'coordinadora@uta.cl')->firstOrFail();
        $this->actingAs($this->coordinadora);
    }

    private function practicaDe(string $nombre): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', $nombre))->firstOrFail();
    }

    public function test_la_lista_general_deriva_los_estados_operativos(): void
    {
        $antonia = $this->practicaDe('Antonia Vega Muñoz');
        HistorialPractica::create([
            'practica_id' => $antonia->id, 'user_id' => $this->coordinadora->id,
            'fecha_hora' => now(), 'estado_nuevo' => 'en_curso',
        ]);
        $josefa = $this->practicaDe('Josefa Ramírez Tapia');
        Alerta::create([
            'practica_id' => $josefa->id, 'coordinadora_id' => $this->coordinadora->id,
            'tipo' => 'abandono', 'fecha_generada' => now(),
        ]);

        $respuesta = $this->getJson('/api/coordinacion/practicas')->assertOk();
        $filas = collect($respuesta->json('data'))->keyBy('estudiante');

        $this->assertSame('2º semestre 2026', $respuesta->json('periodo.nombre'));
        $this->assertSame('por_iniciar', $filas['Camila Rojas Pizarro']['estado']);
        $this->assertSame('Por iniciar', $filas['Camila Rojas Pizarro']['estado_texto']);
        $this->assertSame('en_curso', $filas['Antonia Vega Muñoz']['estado']);
        $this->assertSame('En curso', $filas['Antonia Vega Muñoz']['estado_texto']);
        $this->assertSame('posible_abandono', $filas['Josefa Ramírez Tapia']['estado']);
        $this->assertSame('Posible abandono', $filas['Josefa Ramírez Tapia']['estado_texto']);
        $this->assertSame('Municipalidad de Arica', $filas['Camila Rojas Pizarro']['centro']);
        $this->assertNotEmpty($filas['Camila Rojas Pizarro']['rut']);
        $this->assertSame(['En curso', 'Posible abandono', 'Por iniciar'], array_column($respuesta->json('catalogo.estados'), 'texto'));
        $this->assertContains('Laboral I', array_column($respuesta->json('catalogo.tipos'), 'nombre'));
    }

    public function test_filtra_por_busqueda_tipo_y_estado(): void
    {
        $profesional = TipoPractica::where('nombre', 'Profesional')
            ->whereHas('malla', fn ($q) => $q->whereNotNull('vigente_desde'))
            ->firstOrFail();

        $this->getJson('/api/coordinacion/practicas?q=camila')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante', 'Camila Rojas Pizarro');
        $this->getJson('/api/coordinacion/practicas?q=munoz')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.estudiante', 'Antonia Vega Muñoz');
        $this->getJson("/api/coordinacion/practicas?tipo_practica_id={$profesional->id}&estado=por_iniciar")
            ->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/coordinacion/practicas?estado=posible_abandono')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_valida_los_filtros_y_restringe_el_rol(): void
    {
        $this->getJson('/api/coordinacion/practicas?estado=otro')->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->getJson('/api/coordinacion/practicas?tipo_practica_id=99999')->assertStatus(422)->assertJsonValidationErrors('tipo_practica_id');

        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail())
            ->getJson('/api/coordinacion/practicas')->assertForbidden();
        $this->getJson('/api/coordinacion/practicas/exportar')->assertForbidden();

        Auth::logout();
        $this->getJson('/api/coordinacion/practicas')->assertUnauthorized();
    }

    public function test_exporta_excel_solo_con_los_resultados_filtrados(): void
    {
        $respuesta = $this->get('/api/coordinacion/practicas?estado=por_iniciar&q=camila')
            ->assertOk()
            ->assertDownload('practicas_2026-2.xlsx');

        $lector = new Reader;
        $ruta = $respuesta->baseResponse->getFile()->getPathname();
        $lector->open($ruta);
        $filas = [];
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = array_map(fn ($celda) => (string) $celda, $fila->toArray());
            }
            break;
        }
        $lector->close();

        $this->assertCount(2, $filas);
        $this->assertSame(ListadoPracticasService::ENCABEZADOS, $filas[0]);
        $this->assertSame('Camila Rojas Pizarro', $filas[1][1]);
        $this->assertSame('Por iniciar', $filas[1][6]);
    }
}
