<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicia_sesion_con_credenciales_validas_y_devuelve_el_rol(): void
    {
        User::factory()->create(['email' => 'coordinadora@uta.cl', 'rol' => 'coordinadora']);

        $this->postJson('/api/login', ['email' => 'coordinadora@uta.cl', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'name', 'email', 'rol']])
            ->assertJsonPath('usuario.rol', 'coordinadora');
    }

    public function test_el_estudiante_llega_con_rol_estudiante(): void
    {
        User::factory()->create(['email' => 'estudiante@alumnos.uta.cl', 'rol' => null]);

        $this->postJson('/api/login', ['email' => 'estudiante@alumnos.uta.cl', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'estudiante');
    }

    public function test_rechaza_una_contrasena_incorrecta(): void
    {
        User::factory()->create(['email' => 'jefe@uta.cl', 'rol' => 'jefe']);

        $this->postJson('/api/login', ['email' => 'jefe@uta.cl', 'password' => 'otra'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_rechaza_un_correo_que_no_existe(): void
    {
        $this->postJson('/api/login', ['email' => 'nadie@uta.cl', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_no_deja_entrar_a_una_cuenta_desactivada(): void
    {
        User::factory()->create(['email' => 'egresado@alumnos.uta.cl', 'fecha_baja' => '2026-07-31', 'motivo_baja' => 'Egresado']);

        $this->postJson('/api/login', ['email' => 'egresado@alumnos.uta.cl', 'password' => 'password'])
            ->assertForbidden();
    }

    public function test_me_y_logout_usan_el_token_y_el_logout_lo_invalida(): void
    {
        User::factory()->create(['email' => 'secretaria@uta.cl', 'rol' => 'secretaria']);
        $token = $this->postJson('/api/login', ['email' => 'secretaria@uta.cl', 'password' => 'password'])->json('token');

        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('data.rol', 'secretaria');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
    }

    public function test_sin_token_no_entrega_datos(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }
}
