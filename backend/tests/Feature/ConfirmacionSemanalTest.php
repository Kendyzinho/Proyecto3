<?php

namespace Tests\Feature;

use App\Mail\ConfirmacionSemanalMail;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Services\ConfirmacionSemanalService;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-26: los días se insertan directo en registros_diarios; no depende del código de la HU-13
// HU-26: el supervisor confirma con su cuenta (HU-19). DemoSeeder: Sergio Paredes (con cuenta) supervisa a Antonia;
// Paula Contreras (sin cuenta) a Camila. Los días se insertan directo en registros_diarios .
class ConfirmacionSemanalTest extends TestCase
{
    use BaseDeDatosAislada;

    private Practica $practica;

    private Carbon $lunes;
    private User $sergio;

    private int $diaLunes;

    private int $diaMartes;

    private string $api;

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
        $this->sergio = Supervisor::where('nombre', 'Sergio Paredes')->firstOrFail()->usuario;
        $this->practica = $this->practica('Antonia');
        $this->diaLunes = $this->dia($this->practica, '2026-11-16');
        $this->diaMartes = $this->dia($this->practica, '2026-11-17');
        $this->api = "/api/supervisor/asistencia/{$this->practica->id}/2026-11-16";
    }

    private function practica(string $estudiante): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', 'like', "{$estudiante}%"))->firstOrFail();
    }

    private function dia(string $fecha): int
    private function dia(Practica $practica, string $fecha): int
    {
        return DB::table('registros_diarios')->insertGetId([
            'practica_id' => $this->practica->id, 'fecha' => $fecha,
            'practica_id' => $practica->id, 'fecha' => $fecha,
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
    public function test_el_comando_avisa_al_supervisor_con_cuenta(): void
    {
        $this->dia($this->practica('Camila'), '2026-11-16'); // Paula no tiene cuenta: no recibe aviso

        $this->artisan('supervision:enviar-enlaces')->assertSuccessful();

        Mail::assertQueued(ConfirmacionSemanalMail::class, fn (ConfirmacionSemanalMail $correo) => $correo->hasTo($this->practica->supervisor->correo)
            && str_contains($correo->enlace, "/supervisor/asistencia/{$this->practica->id}/2026-11-16?"));
        Mail::assertQueued(ConfirmacionSemanalMail::class, fn (ConfirmacionSemanalMail $correo) => $correo->hasTo($this->sergio->email)
            && str_ends_with($correo->enlace, "/supervisor/asistencia/{$this->practica->id}/2026-11-16"));
        Mail::assertQueuedCount(1);
    }

    public function test_sin_firma_valida_no_se_puede_ver_ni_confirmar(): void
    public function test_solo_el_supervisor_de_la_practica_puede_verla(): void
    {
        $sinFirma = "/api/supervision/practicas/{$this->practica->id}/semanas/2026-11-16";
        $alterada = str_replace('supervisor='.$this->practica->supervisor_id, 'supervisor=999', $this->api());
        $this->getJson($this->api)->assertUnauthorized();

        $otro = User::create(['name' => 'Otra Supervisora', 'email' => 'otra@example.test', 'rol' => 'supervisor', 'password' => Hash::make('x')]);
        $this->actingAs($otro);
        $this->getJson($this->api)->assertNotFound();
        $this->postJson($this->api, $this->decisiones('confirmado', 'confirmado'))->assertNotFound();
        $this->assertNull($this->validacion($this->diaLunes));
    }

    public function test_el_supervisor_ve_los_dias_de_la_semana(): void
    public function test_el_supervisor_ve_sus_semanas_y_los_dias(): void
    {
        $this->getJson($this->api())->assertOk()
            ->assertJsonPath('data.semana.desde', '2026-11-16')
        $this->actingAs($this->sergio);

        $this->getJson('/api/supervisor/asistencia')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.practica_id', $this->practica->id)
            ->assertJsonPath('data.0.semana.desde', '2026-11-16')
            ->assertJsonPath('data.0.pendientes', 2);

        $this->getJson($this->api)->assertOk()
            ->assertJsonPath('data.confirmada_en', null)
            ->assertJsonCount(2, 'data.dias')
            ->assertJsonPath('data.dias.0.horas', 4);

        $this->getJson("/api/supervisor/asistencia/{$this->practica->id}/2026-11-09")->assertNotFound(); // semana sin días
    }

    public function test_confirma_o_rechaza_cada_dia_y_guarda_quien_y_cuando(): void
    {
        $this->postJson($this->api(), $this->decisiones('confirmado', 'rechazado') + ['observacion' => 'El martes no asistió'])
            ->assertCreated();
        $this->actingAs($this->sergio);

        $this->postJson($this->api, $this->decisiones('confirmado', 'rechazado') + ['observacion' => 'El martes no asistió'])
            ->assertOk()->assertJsonPath('data.pendientes', 0);

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
    public function test_debe_decidir_todos_los_dias_pendientes(): void
    {
        $this->actingAs($this->sergio);

        $this->postJson($this->api, ['dias' => [['id' => $this->diaLunes, 'validacion' => 'confirmado']]])
            ->assertUnprocessable()->assertJsonValidationErrors('dias');

        $this->assertDatabaseCount('confirmaciones_semanales', 0);
        $this->assertNull($this->validacion($this->diaLunes));
    }

    public function test_una_semana_ya_confirmada_no_se_puede_modificar(): void
    public function test_sin_dias_pendientes_no_se_puede_volver_a_enviar(): void
    {
        $this->postJson($this->api(), $this->decisiones('confirmado', 'confirmado'))->assertCreated();
        $this->actingAs($this->sergio);
        $this->postJson($this->api, $this->decisiones('confirmado', 'confirmado'))->assertOk();

        $this->postJson($this->api(), $this->decisiones('rechazado', 'rechazado'))->assertStatus(409);
        $this->postJson($this->api, $this->decisiones('rechazado', 'rechazado'))->assertStatus(409);

        $this->assertSame('confirmado', $this->validacion($this->diaLunes));
        $this->assertSame('confirmado', $this->validacion($this->diaMartes));
        $this->assertDatabaseCount('confirmaciones_semanales', 1);
    }

    public function test_el_enlace_deja_de_servir_si_cambia_el_supervisor(): void
    public function test_un_dia_corregido_tras_el_rechazo_vuelve_a_quedar_pendiente(): void
    {
        $this->actingAs($this->sergio);
        $this->postJson($this->api, $this->decisiones('confirmado', 'rechazado'))->assertOk();

        // HU-13: al corregir un dia rechazado, su validacion vuelve a quedar vacia
        DB::table('registros_diarios')->where('id', $this->diaMartes)->update(['validacion' => null, 'hora_salida' => '14:00']);

        $this->getJson('/api/supervisor/asistencia')->assertJsonPath('data.0.pendientes', 1);
        $this->postJson($this->api, ['dias' => [['id' => $this->diaMartes, 'validacion' => 'confirmado']]])
            ->assertOk()->assertJsonPath('data.pendientes', 0);

        $this->assertSame('confirmado', $this->validacion($this->diaMartes));
        $this->assertDatabaseCount('confirmaciones_semanales', 1); // se conserva la primera confirmacion
    }

    public function test_si_cambia_el_supervisor_el_anterior_deja_de_verla(): void
    {

        $this->getJson($ruta)->assertForbidden();
        $this->actingAs($this->sergio);
        $this->getJson($this->api)->assertNotFound();
    }
}
