<?php

namespace Tests\Feature;

use App\Mail\DocumentosPractica;
use App\Models\CentroPractica;
use App\Models\Documento;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// HU-10: carta de presentación, guía del seguro escolar y acuerdo de colaboración
class DocumentoTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CatalogoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        Storage::fake();
        $this->secretaria = User::where('rol', 'secretaria')->first();
        $this->periodo = Periodo::where('anio', 2026)->where('semestre', 2)->first();
    }

    private function practicaDe(string $nombre): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', 'like', "{$nombre}%"))->firstOrFail();
    }

    public function test_lista_las_practicas_por_formalizar_con_el_estado_de_cada_documento(): void
    {
        $respuesta = $this->actingAs($this->secretaria)->getJson('/api/documentos?periodo_id='.$this->periodo->id)->assertOk();
        $filas = collect($respuesta->json('data'))->keyBy('estudiante');

        $this->assertCount(5, $filas);
        $this->assertSame(['por_generar', 'por_generar'], [$filas['Josefa Ramírez Tapia']['carta']['estado'], $filas['Josefa Ramírez Tapia']['seguro']['estado']]);
        // Corfo tiene el acuerdo en trámite (Art. 15)
        $this->assertSame(['sin_acuerdo', 'en_espera'], [$filas['Diego Choque Vargas']['carta']['estado'], $filas['Diego Choque Vargas']['seguro']['estado']]);
    }

    public function test_el_detalle_trae_la_carta_con_los_datos_de_la_practica(): void
    {
        $practica = $this->practicaDe('Josefa');

        $carta = $this->actingAs($this->secretaria)->getJson("/api/practicas/{$practica->id}/documentos")
            ->assertOk()
            ->assertJsonPath('acuerdo.estado', 'vigente')
            ->json('textos.carta');

        $this->assertSame(['Carolina Mamani Ticona', 'Supervisor(a) de Prácticas', 'Fundación UTA'], $carta['destinatario']);
        $this->assertSame(
            'Presento a Josefa Ramírez Tapia, RUT 21.087.552-2, estudiante de Diseño Multimedia, para realizar su Práctica Profesional '
            .'de 320 horas entre el 7 de septiembre y el 27 de noviembre de 2026, en las tareas acordadas con su institución.',
            $carta['parrafos'][0],
        );
        $this->assertSame('Jefe de Carrera de prueba', $carta['firma'][0]);
    }

    public function test_descarga_la_carta_en_pdf_y_la_registra_una_sola_vez(): void
    {
        $practica = $this->practicaDe('Josefa');

        foreach ([1, 2] as $vez) {
            $respuesta = $this->actingAs($this->secretaria)->get("/api/practicas/{$practica->id}/documentos/carta")->assertOk();
            $this->assertStringStartsWith('%PDF', $respuesta->streamedContent());
        }

        $this->assertSame(1, Documento::where('practica_id', $practica->id)->where('tipo', 'carta')->count());
        $this->actingAs($this->secretaria)->getJson('/api/documentos?periodo_id='.$this->periodo->id)
            ->assertJsonFragment(['estudiante' => 'Josefa Ramírez Tapia', 'carta' => ['estado' => 'generada', 'documento_id' => Documento::first()->id]]);
    }

    public function test_sin_acuerdo_vigente_no_genera_documentos(): void
    {
        $practica = $this->practicaDe('Diego');

        $this->actingAs($this->secretaria)->getJson("/api/practicas/{$practica->id}/documentos/carta")
            ->assertStatus(422)
            ->assertJsonPath('errors.acuerdo.0', 'El centro no tiene un acuerdo de colaboración vigente (Art. 15).');
        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_envia_la_carta_y_la_guia_al_estudiante_y_al_supervisor(): void
    {
        Mail::fake();
        $practica = $this->practicaDe('Josefa');

        $this->actingAs($this->secretaria)->postJson("/api/practicas/{$practica->id}/documentos/enviar")
            ->assertOk()
            ->assertJsonPath('destinatarios', ['josefa.ramirez@alumnos.uta.cl', 'cmamani@fundacionuta.cl']);

        Mail::assertSent(DocumentosPractica::class, fn (DocumentosPractica $correo) => $correo->hasTo('josefa.ramirez@alumnos.uta.cl')
            && $correo->hasTo('cmamani@fundacionuta.cl')
            && count($correo->attachments()) === 2);
        $this->assertSame(2, Documento::where('practica_id', $practica->id)->whereNotNull('fecha_envio')->count());
    }

    public function test_registrar_el_acuerdo_firmado_habilita_al_centro(): void
    {
        $corfo = CentroPractica::where('nombre', 'Corfo Arica y Parinacota')->first();

        $respuesta = $this->actingAs($this->secretaria)->post("/api/centros/{$corfo->id}/acuerdo", [
            'tipo' => 'tradicional',
            'representante_legal' => 'María Pérez Lagos',
            'fecha_inicio' => now()->subMonth()->toDateString(),
            'fecha_termino' => now()->addYears(2)->toDateString(),
            'archivo' => UploadedFile::fake()->create('acuerdo.pdf', 200, 'application/pdf'),
        ])->assertOk()->assertJsonPath('estado', 'vigente');

        $this->assertCount(1, $corfo->acuerdos()->get()); // se actualiza el acuerdo, no se duplica
        $this->actingAs($this->secretaria)->get("/api/acuerdos/{$respuesta->json('id')}/archivo")->assertOk();
        $this->actingAs($this->secretaria)->get("/api/practicas/{$this->practicaDe('Diego')->id}/documentos/carta")->assertOk();
    }

    public function test_el_acuerdo_debe_ser_un_pdf(): void
    {
        $corfo = CentroPractica::where('nombre', 'Corfo Arica y Parinacota')->first();

        $this->actingAs($this->secretaria)->postJson("/api/centros/{$corfo->id}/acuerdo", [
            'tipo' => 'tradicional',
            'representante_legal' => 'María Pérez Lagos',
            'archivo' => UploadedFile::fake()->create('acuerdo.docx', 20),
        ])->assertStatus(422)->assertJsonPath('errors.archivo.0', 'El acuerdo debe ser un PDF.');
    }

    public function test_entrega_el_periodo_en_curso(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 17));

        $this->actingAs(User::where('rol', 'coordinadora')->first())->getJson('/api/periodos/actual')
            ->assertOk()
            ->assertJson(['id' => $this->periodo->id, 'nombre' => '2º semestre 2026']);
    }

    public function test_pueden_entrar_la_secretaria_y_la_coordinadora(): void
    {
        $url = '/api/documentos?periodo_id='.$this->periodo->id;

        $this->actingAs(User::where('rol', 'coordinadora')->first())->getJson($url)->assertOk();
        $this->actingAs(User::where('rol', 'jefe')->first())->getJson($url)->assertForbidden();
        $this->actingAs(User::whereNull('rol')->first())->getJson($url)->assertForbidden();
    }
}
