<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

class AutenticacionTest extends TestCase
{
    use BaseDeDatosAislada;

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

    public function test_logout_sin_token_no_revoca_ninguna_sesion(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('otro-dispositivo');

        $this->postJson('/api/logout')->assertUnauthorized();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_logout_solo_revoca_el_token_actual_y_permite_un_nuevo_login(): void
    {
        $usuario = User::factory()->create(['email' => 'sesiones@uta.cl', 'rol' => 'jefe']);
        $actual = $usuario->createToken('web');
        $otro = $usuario->createToken('otro-dispositivo');

        $this->withToken($actual->plainTextToken)->postJson('/api/logout')->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $actual->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otro->accessToken->id]);

        $this->app['auth']->forgetGuards();
        $this->withToken($actual->plainTextToken)->getJson('/api/me')->assertUnauthorized();
        $this->withToken($actual->plainTextToken)->postJson('/api/logout')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($otro->plainTextToken)->getJson('/api/me')->assertOk();

        $this->app['auth']->forgetGuards();
        $nuevo = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'password'])
            ->assertOk()->json('token');
        $this->app['auth']->forgetGuards();
        $this->withToken($nuevo)->getJson('/api/me')->assertOk();
    }

    public function test_las_respuestas_no_exponen_la_contrasena_ni_tokens_almacenados(): void
    {
        $usuario = User::factory()->create(['email' => 'privacidad@uta.cl', 'rol' => 'jefe']);
        $login = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'password'])->assertOk();
        $datos = ['id' => $usuario->id, 'name' => $usuario->name, 'rut' => $usuario->rut, 'email' => $usuario->email, 'rol' => 'jefe'];
        $this->assertSame($datos, $login->json('usuario'));

        $this->withToken($login->json('token'))->getJson('/api/me')->assertExactJson(['data' => $datos]);
        $this->withToken($login->json('token'))->postJson('/api/logout')->assertNoContent();
    }

    public function test_un_token_expirado_no_permite_me_ni_logout(): void
    {
        $usuario = User::factory()->create();
        $token = $usuario->createToken('expirado', ['*'], now()->subMinute());
        $this->withToken($token->plainTextToken)->getJson('/api/me')->assertUnauthorized();
        $this->withToken($token->plainTextToken)->postJson('/api/logout')->assertUnauthorized();
    }
}
