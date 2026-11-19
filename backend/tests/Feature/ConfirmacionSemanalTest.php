<?php

namespace Tests\Feature;

use App\Mail\ConfirmacionSemanalMail;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Services\ConfirmacionSemanalService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-26: los días se insertan directo en registros_diarios; no depende del código de la HU-13
class ConfirmacionSemanalTest extends TestCase
{
    use BaseDeDatosAislada;

    private Practica $practica;

    private Carbon $lunes;

    private int $diaLunes;

    private int $diaMartes;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-23 08:00')); // lunes siguiente a la semana registrada
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);

        $this->practica = Practica::formalizadas()->whereNotNull('supervisor_id')->firstOrFail();
        $this->lunes = Carbon::parse('2026-11-16');
        $this->diaLunes = $this->dia('2026-11-16');
        $this->diaMartes = $this->dia('2026-11-17');
    }

    private function dia(string $fecha): int
    {
        return DB::table('registros_diarios')->insertGetId([
            'practica_id' => $this->practica->id, 'fecha' => $fecha,
            'hora_entrada' => '09:00', 'hora_salida' => '13:00', 'actividades' => 'Diseño de piezas gráficas',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function validacion(int $id): ?string
    {
        return DB::table('registros_diarios')->where('id', $id)->value('validacion');
    }

    // Convierte el enlace del correo en la ruta de la API que llama Angular
    private function api(): string
    {
        $enlace = app(ConfirmacionSemanalService::class)->enlace($this->practica, $this->lunes);

        return "/api/supervision/practicas/{$this->practica->id}/semanas/2026-11-16?".parse_url($enlace, PHP_URL_QUERY);
    }

    private function decisiones(string $lunes, string $martes): array
    {
        return ['dias' => [
            ['id' => $this->diaLunes, 'validacion' => $lunes],
            ['id' => $this->diaMartes, 'validacion' => $martes],
        ]];
    }

    public function test_el_comando_envia_el_enlace_al_supervisor_de_la_practica(): void
    {
        $this->artisan('supervision:enviar-enlaces')->assertSuccessful();

        Mail::assertQueued(ConfirmacionSemanalMail::class, fn (ConfirmacionSemanalMail $correo) => $correo->hasTo($this->practica->supervisor->correo)
            && str_contains($correo->enlace, "/supervisor/asistencia/{$this->practica->id}/2026-11-16?"));
        Mail::assertQueuedCount(1);
    }

    public function test_sin_firma_valida_no_se_puede_ver_ni_confirmar(): void
    {
        $sinFirma = "/api/supervision/practicas/{$this->practica->id}/semanas/2026-11-16";
        $alterada = str_replace('supervisor='.$this->practica->supervisor_id, 'supervisor=999', $this->api());

        $this->getJson($sinFirma)->assertForbidden();
        $this->getJson($alterada)->assertForbidden();
        $this->postJson($sinFirma, $this->decisiones('confirmado', 'confirmado'))->assertForbidden();
    }

    public function test_el_supervisor_ve_los_dias_de_la_semana(): void
    {
        $this->getJson($this->api())->assertOk()
            ->assertJsonPath('data.semana.desde', '2026-11-16')
            ->assertJsonPath('data.confirmada_en', null)
            ->assertJsonCount(2, 'data.dias')
            ->assertJsonPath('data.dias.0.horas', 4);
    }

    public function test_confirma_o_rechaza_cada_dia_y_guarda_quien_y_cuando(): void
    {
        $this->postJson($this->api(), $this->decisiones('confirmado', 'rechazado') + ['observacion' => 'El martes no asistió'])
            ->assertCreated();

        $this->assertSame('confirmado', $this->validacion($this->diaLunes));
        $this->assertSame('rechazado', $this->validacion($this->diaMartes));
        $this->assertDatabaseHas('confirmaciones_semanales', [
            'practica_id' => $this->practica->id,
            'supervisor_id' => $this->practica->supervisor_id,
            'fecha_hora_envio' => '2026-11-23 08:00:00',
            'observacion' => 'El martes no asistió',
        ]);
    }

    public function test_debe_decidir_todos_los_dias_registrados(): void
    {
        $this->postJson($this->api(), ['dias' => [['id' => $this->diaLunes, 'validacion' => 'confirmado']]])
            ->assertUnprocessable()->assertJsonValidationErrors('dias');

        $this->assertDatabaseCount('confirmaciones_semanales', 0);
        $this->assertNull($this->validacion($this->diaLunes));
    }

    public function test_una_semana_ya_confirmada_no_se_puede_modificar(): void
    {
        $this->postJson($this->api(), $this->decisiones('confirmado', 'confirmado'))->assertCreated();

        $this->postJson($this->api(), $this->decisiones('rechazado', 'rechazado'))->assertStatus(409);

        $this->assertSame('confirmado', $this->validacion($this->diaLunes));
        $this->assertSame('confirmado', $this->validacion($this->diaMartes));
        $this->assertDatabaseCount('confirmaciones_semanales', 1);
    }

    public function test_el_enlace_deja_de_servir_si_cambia_el_supervisor(): void
    {
        $ruta = $this->api();
        $otro = Supervisor::whereKeyNot($this->practica->supervisor_id)->firstOrFail();
        $this->practica->update(['supervisor_id' => $otro->id]);

        $this->getJson($ruta)->assertForbidden();
    }

    public function test_sin_respuesta_por_mas_de_una_semana_genera_la_alerta(): void
    {
        $this->artisan('supervision:alertar-sin-confirmar')->assertSuccessful();
        $this->assertDatabaseCount('alertas', 0); // el enlace recién sale hoy

        $this->travelTo(Carbon::parse('2026-11-30 08:30'));
        $this->artisan('supervision:alertar-sin-confirmar')->assertSuccessful();
        $this->artisan('supervision:alertar-sin-confirmar')->assertSuccessful(); // no duplica

        $this->assertDatabaseCount('alertas', 1);
        $this->assertDatabaseHas('alertas', ['practica_id' => $this->practica->id, 'tipo' => 'sin_confirmar']);
    }
}
