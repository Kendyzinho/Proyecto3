<?php

namespace Tests\Feature;

use App\Mail\InformeConfidencialMail;
use App\Mail\InvitacionSupervisorMail;
use App\Models\HistorialPractica;
use App\Models\Item;
use App\Models\Practica;
use App\Models\RespuestaInstrumento;
use App\Models\Supervisor;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\InstrumentoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-19: informe confidencial del supervisor(a). DemoSeeder: Sergio Paredes (con cuenta) supervisa a Antonia (Laboral II)
// y Pedro (Profesional); Paula Contreras (sin cuenta) a Camila (Laboral I).
class InformeConfidencialTest extends TestCase
{
    use BaseDeDatosAislada;

    private User $coordinadora;

    private User $sergio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class, InstrumentoSeeder::class]);
        $this->coordinadora = User::where('email', 'coordinadora@uta.cl')->firstOrFail();
        $this->sergio = Supervisor::where('nombre', 'Sergio Paredes')->firstOrFail()->usuario;
        $this->actingAs($this->coordinadora);
    }

    private function practica(string $estudiante): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', $estudiante))->firstOrFail();
    }

    private function solicitar(Practica $practica)
    {
        return $this->postJson("/api/coordinacion/informes/{$practica->id}/solicitar");
    }

    private function fila(string $estudiante): array
    {
        return collect($this->getJson('/api/coordinacion/informes')->assertOk()->json('data'))->firstWhere('estudiante', $estudiante);
    }

    // Todas las preguntas de escala con "De acuerdo" (4) y, si se pide, el comentario
    private function respuestas(Practica $practica, ?int $soloEscalas = null, bool $conComentario = true): array
    {
        $respuesta = RespuestaInstrumento::where('practica_id', $practica->id)->whereNull('visita_id')->firstOrFail();
        $filas = [];
        foreach (Item::with('opciones')->where('plantilla_id', $respuesta->plantilla_id)->orderBy('orden')->get() as $item) {
            if ($item->tipo_respuesta === 'texto') {
                if ($conComentario) {
                    $filas[] = ['item_id' => $item->id, 'texto' => 'Muy comprometida con el equipo.'];
                }

                continue;
            }
            if ($soloEscalas !== null && count($filas) >= $soloEscalas) {
                continue;
            }
            $filas[] = ['item_id' => $item->id, 'opcion_id' => $item->opciones->firstWhere('valor_numerico', 4)->id];
        }

        return $filas;
    }

    private function guardar(Practica $practica, array $respuestas, bool $enviar)
    {
        return $this->putJson("/api/supervisor/informes/{$practica->id}", ['respuestas' => $respuestas, 'enviar' => $enviar]);
    }

    public function test_la_coordinacion_ve_las_practicas_con_su_estado_de_informe(): void
    {
        $antonia = $this->fila('Antonia Vega Muñoz');

        $this->assertSame('sin_solicitar', $antonia['estado']);
        $this->assertSame('Laboral II', $antonia['tipo_practica']);
        $this->assertSame('Productora Norte Visual', $antonia['centro']);
        $this->assertSame('Sergio Paredes', $antonia['supervisor']);
        $this->assertCount(5, $this->getJson('/api/coordinacion/informes')->json('data'));
    }

    public function test_solicitar_crea_el_informe_con_la_plantilla_de_su_tipo_y_avisa_al_supervisor(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');

        $this->solicitar($antonia)->assertOk()->assertJsonPath('data.estado', 'solicitado');

        Mail::assertSent(InformeConfidencialMail::class, fn ($correo) => $correo->hasTo($this->sergio->email)
            && str_ends_with($correo->enlace, "/supervisor/informes/{$antonia->id}"));
        Mail::assertNotSent(InvitacionSupervisorMail::class); // Sergio ya tenía cuenta

        $this->actingAs($this->sergio);
        $laboral = $this->getJson("/api/supervisor/informes/{$antonia->id}")->assertOk()->json('data');
        $this->assertCount(18, $laboral['informe']['items']);
        $this->assertSame('Antonia Vega Muñoz', $laboral['practica']['estudiante']);
        $this->assertSame(
            ['Totalmente en desacuerdo', 'En desacuerdo', 'Ni de acuerdo ni en desacuerdo', 'De acuerdo', 'Totalmente de acuerdo', 'No aplica'],
            array_column($laboral['informe']['items'][0]['opciones'], 'etiqueta'),
        );

        $this->actingAs($this->coordinadora);
        $pedro = $this->practica('Pedro Lagos Rivas');
        $this->solicitar($pedro)->assertOk();
        $this->actingAs($this->sergio);
        $this->assertCount(22, $this->getJson("/api/supervisor/informes/{$pedro->id}")->json('data.informe.items'));
    }

    public function test_solicitar_de_nuevo_reenvia_el_aviso_sin_duplicar(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');
        $this->solicitar($antonia)->assertOk();
        $this->solicitar($antonia)->assertOk();

        $this->assertSame(1, RespuestaInstrumento::where('practica_id', $antonia->id)->count());
        Mail::assertSent(InformeConfidencialMail::class, 2);
    }

    public function test_un_supervisor_sin_cuenta_la_recibe_al_solicitar(): void
    {
        $camila = $this->practica('Camila Rojas Pizarro');

        $this->solicitar($camila)->assertOk();

        $paula = $camila->supervisor->fresh();
        $this->assertNotNull($paula->user_id);
        Mail::assertSent(InvitacionSupervisorMail::class, 1);
        Mail::assertSent(InformeConfidencialMail::class, 1);
    }

    public function test_sin_supervisor_activo_no_se_solicita(): void
    {
        $camila = $this->practica('Camila Rojas Pizarro');
        $camila->supervisor->update(['fecha_baja' => '2026-11-20']);

        $this->solicitar($camila)->assertStatus(422)->assertJsonValidationErrors('practica');
        Mail::assertNothingSent();
    }

    public function test_el_supervisor_guarda_un_borrador_y_despues_lo_envia(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');
        $this->solicitar($antonia)->assertOk();
        $this->actingAs($this->sergio);

        $this->guardar($antonia, $this->respuestas($antonia, soloEscalas: 3, conComentario: false), enviar: false)->assertOk();
        $this->assertCount(3, $this->getJson("/api/supervisor/informes/{$antonia->id}")->json('data.informe.respuestas'));

        $this->guardar($antonia, $this->respuestas($antonia, soloEscalas: 3), enviar: true)
            ->assertStatus(422)->assertJsonValidationErrors('respuestas');

        // El comentario es opcional
        $this->guardar($antonia, $this->respuestas($antonia, conComentario: false), enviar: true)->assertOk();
        $this->guardar($antonia, $this->respuestas($antonia), enviar: false)->assertStatus(409);

        $this->assertSame('enviado', collect($this->getJson('/api/supervisor/informes')->json('data'))->firstWhere('id', $antonia->id)['estado']);
    }

    public function test_el_supervisor_solo_ve_los_informes_solicitados_de_sus_practicas(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');
        $pedro = $this->practica('Pedro Lagos Rivas');
        $camila = $this->practica('Camila Rojas Pizarro');
        $this->solicitar($antonia)->assertOk();
        $this->solicitar($camila)->assertOk();
        $this->actingAs($this->sergio);

        $this->assertSame(['Antonia Vega Muñoz'], array_column($this->getJson('/api/supervisor/informes')->assertOk()->json('data'), 'estudiante'));
        $this->getJson("/api/supervisor/informes/{$pedro->id}")->assertNotFound(); // suya, pero no solicitado
        $this->getJson("/api/supervisor/informes/{$camila->id}")->assertNotFound(); // de otro supervisor
        $this->guardar($camila, [], enviar: false)->assertNotFound();
    }

    public function test_la_coordinacion_lee_el_informe_solo_cuando_esta_enviado(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');
        $this->solicitar($antonia)->assertOk();
        $this->getJson("/api/coordinacion/informes/{$antonia->id}")->assertNotFound();

        $this->actingAs($this->sergio);
        $this->guardar($antonia, $this->respuestas($antonia), enviar: true)->assertOk();

        $this->actingAs($this->coordinadora);
        $informe = $this->getJson("/api/coordinacion/informes/{$antonia->id}")->assertOk()->json('data');
        $this->assertNotNull($informe['informe']['enviada_en']);
        $this->assertCount(18, $informe['informe']['respuestas']);
        $this->assertSame('enviado', $this->fila('Antonia Vega Muñoz')['estado']);
    }

    public function test_el_estudiante_y_la_secretaria_no_acceden(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');
        $this->solicitar($antonia)->assertOk();

        $this->actingAs($antonia->habilitacion->estudiante->usuario);
        $this->getJson('/api/supervisor/informes')->assertForbidden();
        $this->getJson("/api/supervisor/informes/{$antonia->id}")->assertForbidden();
        $this->getJson("/api/coordinacion/informes/{$antonia->id}")->assertForbidden();

        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail());
        $this->getJson('/api/coordinacion/informes')->assertForbidden();
    }

    public function test_un_informe_ya_enviado_no_se_vuelve_a_solicitar(): void
    {
        $antonia = $this->practica('Antonia Vega Muñoz');
        $this->solicitar($antonia)->assertOk();
        $this->actingAs($this->sergio);
        $this->guardar($antonia, $this->respuestas($antonia), enviar: true)->assertOk();

        $this->actingAs($this->coordinadora);
        $this->solicitar($antonia)->assertStatus(422)->assertJsonValidationErrors('practica');
        Mail::assertSent(InformeConfidencialMail::class, 1);
    }

    public function test_si_el_correo_falla_el_informe_queda_solicitado_y_lo_avisa(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Servidor de correo caído'));
        $antonia = $this->practica('Antonia Vega Muñoz');

        $this->solicitar($antonia)->assertOk()
            ->assertJsonPath('data.estado', 'solicitado')
            ->assertJsonPath('message', 'El informe quedó solicitado, pero no se pudo enviar el correo. Inténtalo de nuevo con "Reenviar aviso".');
    }

    public function test_dado_de_baja_con_un_informe_pendiente_puede_responderlo_y_despues_se_cierra_su_cuenta(): void
    {
        // Paula (Municipalidad) solo supervisa a Camila: se le pide el informe, la práctica termina y la dan de baja
        $camila = $this->practica('Camila Rojas Pizarro');
        $this->solicitar($camila)->assertOk();
        $paula = $camila->supervisor->fresh();
        HistorialPractica::create(['practica_id' => $camila->id, 'user_id' => $this->coordinadora->id, 'fecha_hora' => now(), 'estado_nuevo' => 'finalizada']);
        $this->postJson("/api/centros-practica/{$paula->centro_id}/supervisores/{$paula->id}/dar-de-baja")->assertOk();

        $cuenta = $paula->usuario->fresh();
        $this->assertNull($cuenta->fecha_baja); // le falta responder el informe
        $this->solicitar($camila)->assertOk(); // y se le puede reenviar el aviso

        $this->actingAs($cuenta);
        $this->guardar($camila, $this->respuestas($camila), enviar: true)->assertOk();

        $this->assertNotNull($cuenta->fresh()->fecha_baja);
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_el_jefe_tambien_solicita_y_lee(): void
    {
        $this->actingAs(User::where('email', 'jefe@uta.cl')->firstOrFail());

        $this->solicitar($this->practica('Antonia Vega Muñoz'))->assertOk();
        $this->getJson('/api/coordinacion/informes')->assertOk();
    }
}
