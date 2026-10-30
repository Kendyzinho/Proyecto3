<?php

namespace Tests\Feature;

use App\Mail\RecuperarContrasena;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-02: recuperación autónoma de contraseña con enlace temporal de 24 horas
class RecuperacionContrasenaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const RESPUESTA = 'Si el correo pertenece a una cuenta activa, te enviamos un enlace para restablecer la contraseña.';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.frontend_url' => 'http://localhost:4200']);
    }

    // Pide el enlace y devuelve el token que viaja en él
    private function pedirEnlace(string $email): string
    {
        $this->postJson('/api/recuperar-contrasena', ['email' => $email])->assertOk();

        $enlace = null;
        Mail::assertSent(RecuperarContrasena::class, function (RecuperarContrasena $correo) use (&$enlace) {
            $enlace = $correo->enlace;

            return true;
        });
        parse_str((string) parse_url($enlace, PHP_URL_QUERY), $parametros);

        return $parametros['token'];
    }

    private function restablecer(string $token, string $email, string $clave = 'NuevaClave1'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/restablecer-contrasena', [
            'token' => $token, 'email' => $email, 'password' => $clave, 'password_confirmation' => $clave,
        ]);
    }

    public function test_envia_al_correo_un_enlace_a_la_pantalla_de_angular(): void
    {
        $usuario = User::factory()->create(['email' => 'secretaria@uta.cl', 'rol' => 'secretaria']);

        $this->postJson('/api/recuperar-contrasena', ['email' => 'secretaria@uta.cl'])
            ->assertOk()
            ->assertJsonPath('message', self::RESPUESTA);

        Mail::assertSent(RecuperarContrasena::class, fn (RecuperarContrasena $correo) => $correo->hasTo($usuario->email)
            && str_starts_with($correo->enlace, 'http://localhost:4200/auth/restablecer?token=')
            && str_contains($correo->enlace, 'email=secretaria%40uta.cl'));
    }

    public function test_responde_igual_si_el_correo_no_existe_y_no_envia_nada(): void
    {
        $this->postJson('/api/recuperar-contrasena', ['email' => 'nadie@uta.cl'])
            ->assertOk()
            ->assertJsonPath('message', self::RESPUESTA);

        Mail::assertNothingSent();
    }

    public function test_una_cuenta_desactivada_no_recibe_el_enlace(): void
    {
        User::factory()->create(['email' => 'egresado@alumnos.uta.cl', 'fecha_baja' => '2026-07-31', 'motivo_baja' => 'Egresado']);

        $this->postJson('/api/recuperar-contrasena', ['email' => 'egresado@alumnos.uta.cl'])
            ->assertOk()
            ->assertJsonPath('message', self::RESPUESTA);

        Mail::assertNothingSent();
    }

    public function test_con_el_enlace_cambia_la_clave_y_cierra_las_sesiones_abiertas(): void
    {
        $usuario = User::factory()->create(['email' => 'jefe@uta.cl', 'rol' => 'jefe']);
        $sesion = $usuario->createToken('web')->plainTextToken;
        $token = $this->pedirEnlace('jefe@uta.cl');

        $this->restablecer($token, 'jefe@uta.cl')->assertNoContent();

        $this->assertTrue(Hash::check('NuevaClave1', $usuario->fresh()->password));
        $this->assertSame(0, $usuario->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($sesion)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => 'jefe@uta.cl', 'password' => 'NuevaClave1'])->assertOk();
    }

    public function test_el_enlace_sigue_valido_antes_de_las_24_horas(): void
    {
        User::factory()->create(['email' => 'coordinadora@uta.cl', 'rol' => 'coordinadora']);
        $token = $this->pedirEnlace('coordinadora@uta.cl');

        $this->travel(23)->hours();

        $this->restablecer($token, 'coordinadora@uta.cl')->assertNoContent();
    }

    public function test_un_enlace_vencido_no_cambia_la_clave(): void
    {
        $usuario = User::factory()->create(['email' => 'coordinadora@uta.cl', 'rol' => 'coordinadora']);
        $token = $this->pedirEnlace('coordinadora@uta.cl');

        $this->travel(24)->hours();
        $this->travel(1)->minutes();

        $this->restablecer($token, 'coordinadora@uta.cl')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'El enlace no es válido o ya venció. Pide uno nuevo.']);
        $this->assertTrue(Hash::check('password', $usuario->fresh()->password));
    }

    public function test_un_enlace_ya_usado_no_cambia_la_clave(): void
    {
        $usuario = User::factory()->create(['email' => 'secretaria@uta.cl', 'rol' => 'secretaria']);
        $token = $this->pedirEnlace('secretaria@uta.cl');
        $this->restablecer($token, 'secretaria@uta.cl')->assertNoContent();

        $this->restablecer($token, 'secretaria@uta.cl', 'OtraClave22')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
        $this->assertTrue(Hash::check('NuevaClave1', $usuario->fresh()->password));
    }

    public function test_un_token_inventado_o_de_otro_correo_no_sirve(): void
    {
        User::factory()->create(['email' => 'jefe@uta.cl', 'rol' => 'jefe']);
        User::factory()->create(['email' => 'secretaria@uta.cl', 'rol' => 'secretaria']);
        $token = $this->pedirEnlace('jefe@uta.cl');

        $this->restablecer('inventado', 'jefe@uta.cl')->assertJsonValidationErrors('token');
        $this->restablecer($token, 'secretaria@uta.cl')->assertJsonValidationErrors('token');
    }

    public function test_exige_una_clave_compleja_y_confirmada(): void
    {
        User::factory()->create(['email' => 'jefe@uta.cl', 'rol' => 'jefe']);
        $token = $this->pedirEnlace('jefe@uta.cl');

        $this->restablecer($token, 'jefe@uta.cl', 'corta1')->assertJsonValidationErrors(['password' => 'Usa al menos 8 caracteres.']);
        $this->restablecer($token, 'jefe@uta.cl', 'sinmayuscula1')
            ->assertJsonValidationErrors(['password' => 'Incluye al menos una letra mayúscula y un número.']);
        $this->postJson('/api/restablecer-contrasena', [
            'token' => $token, 'email' => 'jefe@uta.cl', 'password' => 'NuevaClave1', 'password_confirmation' => 'NuevaClave2',
        ])->assertJsonValidationErrors(['password' => 'La confirmación no coincide con la nueva contraseña.']);

        // Ningún intento fallido gastó el enlace
        $this->restablecer($token, 'jefe@uta.cl')->assertNoContent();
    }
}
