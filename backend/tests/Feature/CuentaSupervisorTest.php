<?php

namespace Tests\Feature;

use App\Mail\InvitacionSupervisorMail;
use App\Models\CentroPractica;
use App\Models\Supervisor;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-19: el supervisor(a) es un usuario externo con cuenta propia, que se crea al activarlo en el centro
class CuentaSupervisorTest extends TestCase
{
    use BaseDeDatosAislada;

    private CentroPractica $productora;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->travelTo(Carbon::parse('2026-11-25 10:00'));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->actingAs(User::where('email', 'coordinadora@uta.cl')->firstOrFail());
        $this->productora = CentroPractica::where('nombre', 'Productora Norte Visual')->firstOrFail();
    }

    // Supervisor propuesto (sin activar) en un centro
    private function nuevo(string $correo, ?CentroPractica $centro = null): Supervisor
    {
        $centro ??= $this->productora;
        $this->postJson("/api/centros-practica/{$centro->id}/supervisores", ['nombre' => 'Marta Soto Rivera', 'correo' => $correo])->assertCreated();

        return Supervisor::where('centro_id', $centro->id)->where('correo', $correo)->firstOrFail();
    }

    private function activar(Supervisor $supervisor)
    {
        return $this->postJson("/api/centros-practica/{$supervisor->centro_id}/supervisores/{$supervisor->id}/activar");
    }

    private function darDeBaja(Supervisor $supervisor)
    {
        return $this->postJson("/api/centros-practica/{$supervisor->centro_id}/supervisores/{$supervisor->id}/dar-de-baja");
    }

    public function test_activar_crea_la_cuenta_e_invita_a_crear_la_contrasena(): void
    {
        $supervisor = $this->nuevo('Marta.Soto@Example.test');

        $this->activar($supervisor)->assertOk();

        $cuenta = User::where('email', 'marta.soto@example.test')->firstOrFail();
        $this->assertSame('supervisor', $cuenta->rol);
        $this->assertSame('Marta Soto Rivera', $cuenta->name);
        $this->assertSame($cuenta->id, $supervisor->fresh()->user_id);
        Mail::assertSent(InvitacionSupervisorMail::class, fn ($correo) => $correo->hasTo('marta.soto@example.test')
            && str_contains($correo->enlace, '/auth/restablecer?token='));
    }

    public function test_si_la_invitacion_falla_queda_activado_y_lo_avisa(): void
    {
        $supervisor = $this->nuevo('marta@example.test');
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Servidor de correo caído'));

        $this->activar($supervisor)->assertOk()
            ->assertJsonPath('message', 'Supervisor(a) activado. No se pudo enviar el correo: puede crear su contraseña con "¿Olvidaste tu contraseña?".');

        $this->assertNotNull($supervisor->fresh()->fecha_alta);
        $this->assertNotNull($supervisor->fresh()->user_id);
    }

    public function test_el_catalogo_no_expone_la_cuenta(): void
    {
        $supervisor = $this->nuevo('marta@example.test');
        $this->activar($supervisor)->assertOk();

        $supervisores = $this->getJson("/api/centros-practica/{$this->productora->id}")->assertOk()->json('data.supervisores');
        $this->assertArrayNotHasKey('user_id', collect($supervisores)->firstWhere('correo', 'marta@example.test'));
    }

    public function test_un_correo_de_cuenta_interna_no_se_puede_activar(): void
    {
        $supervisor = $this->nuevo('secretaria@uta.cl');

        $this->activar($supervisor)->assertStatus(422)->assertJsonValidationErrors('correo');

        $this->assertNull($supervisor->fresh()->fecha_alta);
        $this->assertSame('secretaria', User::where('email', 'secretaria@uta.cl')->value('rol'));
        Mail::assertNothingSent();
    }

    public function test_la_misma_persona_en_otro_centro_usa_la_misma_cuenta(): void
    {
        $this->activar($this->nuevo('marta@example.test'))->assertOk();
        $municipalidad = CentroPractica::where('nombre', 'Municipalidad de Arica')->firstOrFail();

        $this->activar($this->nuevo('marta@example.test', $municipalidad))->assertOk();

        $this->assertSame(1, User::where('email', 'marta@example.test')->count());
        $this->assertSame(2, User::where('email', 'marta@example.test')->firstOrFail()->supervisores()->count());
        Mail::assertSent(InvitacionSupervisorMail::class, 1);
    }

    public function test_la_baja_desactiva_la_cuenta_si_no_le_queda_nada_activo(): void
    {
        $supervisor = $this->nuevo('marta@example.test');
        $this->activar($supervisor)->assertOk();
        $cuenta = $supervisor->fresh()->usuario;
        $cuenta->createToken('web');

        $this->darDeBaja($supervisor)->assertOk();

        $this->assertNotNull($cuenta->fresh()->fecha_baja);
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_la_baja_no_desactiva_la_cuenta_si_supervisa_en_otro_centro(): void
    {
        $primero = $this->nuevo('marta@example.test');
        $this->activar($primero)->assertOk();
        $municipalidad = CentroPractica::where('nombre', 'Municipalidad de Arica')->firstOrFail();
        $this->activar($this->nuevo('marta@example.test', $municipalidad))->assertOk();

        $this->darDeBaja($primero)->assertOk();

        $this->assertNull(User::where('email', 'marta@example.test')->value('fecha_baja'));
    }

    public function test_la_baja_no_desactiva_la_cuenta_si_tiene_practicas_en_curso(): void
    {
        // Sergio Paredes supervisa a Pedro y Antonia (confirmadas)
        $sergio = Supervisor::where('nombre', 'Sergio Paredes')->firstOrFail();
        $this->artisan('supervisores:crear-cuentas')->assertSuccessful();

        $this->darDeBaja($sergio)->assertOk();

        $this->assertNull($sergio->fresh()->usuario->fecha_baja);
    }

    public function test_una_cuenta_desactivada_se_reactiva_al_volver_a_activarlo(): void
    {
        $this->activar($this->nuevo('marta@example.test'))->assertOk();
        $cuenta = User::where('email', 'marta@example.test')->firstOrFail();
        $cuenta->update(['fecha_baja' => '2026-11-20', 'motivo_baja' => 'Baja anterior']);
        $municipalidad = CentroPractica::where('nombre', 'Municipalidad de Arica')->firstOrFail();

        $this->activar($this->nuevo('marta@example.test', $municipalidad))->assertOk();

        $this->assertNull($cuenta->fresh()->fecha_baja);
        $this->assertNull($cuenta->fresh()->motivo_baja);
        Mail::assertSent(InvitacionSupervisorMail::class, 2);
    }

    public function test_el_comando_crea_las_cuentas_de_los_activos_sin_cuenta(): void
    {
        $activos = Supervisor::whereNull('user_id')->get()->filter->estaActivo();
        $this->assertNotEmpty($activos);

        $this->artisan('supervisores:crear-cuentas')->assertSuccessful();

        foreach ($activos as $supervisor) {
            $this->assertNotNull($supervisor->fresh()->user_id, $supervisor->nombre);
        }
        $this->assertNull($this->nuevo('marta@example.test')->user_id);
    }

    public function test_usuarios_no_lista_ni_administra_supervisores(): void
    {
        $this->activar($this->nuevo('marta@example.test'))->assertOk();
        $cuenta = User::where('email', 'marta@example.test')->firstOrFail();
        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail());

        $correos = array_column($this->getJson('/api/usuarios')->assertOk()->json('data'), 'email');
        $this->assertNotContains('marta@example.test', $correos);
        $this->assertContains('secretaria@uta.cl', $correos);

        $this->putJson("/api/usuarios/{$cuenta->id}", ['name' => 'Otra', 'email' => 'otra@example.test', 'rol' => 'secretaria'])->assertStatus(422);
        $this->postJson("/api/usuarios/{$cuenta->id}/desactivar", ['motivo' => 'Prueba'])->assertStatus(422);
        $this->postJson("/api/usuarios/{$cuenta->id}/reactivar")->assertStatus(422);
        $this->assertSame('supervisor', $cuenta->fresh()->rol);
    }

    public function test_el_supervisor_no_entra_a_rutas_internas(): void
    {
        $this->activar($this->nuevo('marta@example.test'))->assertOk();
        $this->actingAs(User::where('email', 'marta@example.test')->firstOrFail());

        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.rol', 'supervisor');
        $this->getJson('/api/coordinacion/visitas')->assertForbidden();
        $this->getJson('/api/centros-practica')->assertForbidden();
        $this->getJson("/api/centros-practica/{$this->productora->id}")->assertForbidden();
        $this->getJson('/api/periodos')->assertForbidden();
        $this->getJson('/api/solicitudes-practica')->assertForbidden();
        $this->getJson('/api/practicas/solicitud-profesional')->assertForbidden();
    }
}
