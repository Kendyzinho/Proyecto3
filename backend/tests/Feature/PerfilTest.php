<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use BaseDeDatosAislada;

    private function autenticar(array $atributos = []): User
    {
        $usuario = User::factory()->create($atributos);
        $this->withToken($usuario->createToken('web')->plainTextToken);

        return $usuario;
    }

    private function cambio(array $atributos = []): array
    {
        return array_replace([
            'current_password' => 'password',
            'password' => 'NuevaClave2026',
            'password_confirmation' => 'NuevaClave2026',
        ], $atributos);
    }

    public static function roles(): array
    {
        return [['secretaria', 'secretaria'], ['coordinadora', 'coordinadora'], ['jefe', 'jefe'], [null, 'estudiante']];
    }

    #[DataProvider('roles')]
    public function test_cada_rol_consulta_solo_su_perfil(?string $rol, string $rolApi): void
    {
        $usuario = $this->autenticar(['rol' => $rol, 'rut' => '11.111.111-1']);
        $otro = User::factory()->create();

        $this->getJson('/api/me?user_id='.$otro->id)->assertOk()->assertExactJson(['data' => [
            'id' => $usuario->id, 'name' => $usuario->name, 'rut' => $usuario->rut,
            'email' => $usuario->email, 'rol' => $rolApi,
        ]]);
    }

    public function test_el_rut_puede_ser_nulo(): void
    {
        $this->autenticar(['rut' => null]);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.rut', null);
    }

    public function test_sin_autenticacion_no_se_consulta_ni_modifica_el_perfil(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->putJson('/api/me/password', $this->cambio())->assertUnauthorized();
    }

    public function test_cambia_el_hash_conserva_la_sesion_y_solo_acepta_la_nueva_clave_en_el_login(): void
    {
        $usuario = $this->autenticar();
        $hashAnterior = $usuario->password;
        $otroToken = $usuario->createToken('otro-dispositivo');
        $this->putJson('/api/me/password', $this->cambio())->assertNoContent();

        $hashNuevo = $usuario->fresh()->password;
        $this->assertNotSame($hashAnterior, $hashNuevo);
        $this->assertNotSame($this->cambio()['password'], $hashNuevo);
        $this->assertTrue(Hash::check($this->cambio()['password'], $hashNuevo));
        $this->assertFalse(Hash::check($this->cambio()['current_password'], $hashNuevo));
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $otroToken->accessToken->id]);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $usuario->id);
        $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/login', ['email' => $usuario->email, 'password' => $this->cambio()['password']])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/logout')->assertNoContent();
    }

    public function test_rechaza_la_contrasena_actual_incorrecta_sin_exponer_claves_o_hashes(): void
    {
        $usuario = $this->autenticar();
        $datos = $this->cambio(['current_password' => 'ActualIncorrecta123']);
        $respuesta = $this->putJson('/api/me/password', $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($usuario->password, $usuario->fresh()->password);
        $this->assertStringNotContainsString($datos['current_password'], $respuesta->getContent());
        $this->assertStringNotContainsString($datos['password'], $respuesta->getContent());
        $this->assertStringNotContainsString($usuario->password, $respuesta->getContent());
    }

    public function test_exige_los_tres_campos(): void
    {
        $this->autenticar();
        $this->putJson('/api/me/password', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password', 'password', 'password_confirmation']);
    }

    public static function clavesInvalidas(): array
    {
        return [
            'corta' => ['Abc123'],
            'sin mayuscula' => ['nuevaclave2026'],
            'sin numero' => ['NuevaClaveLarga'],
            'mas de 72 bytes' => [str_repeat('a', 70).'Ab1'],
            'unicode mas de 72 bytes' => [str_repeat('á', 36).'A1'],
            'caracter nulo' => ["Nueva\0Clave2026"],
        ];
    }

    #[DataProvider('clavesInvalidas')]
    public function test_rechaza_una_nueva_contrasena_invalida(string $clave): void
    {
        $usuario = $this->autenticar();
        $this->putJson('/api/me/password', $this->cambio(['password' => $clave, 'password_confirmation' => $clave]))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($usuario->password, $usuario->fresh()->password);
    }

    public function test_rechaza_una_confirmacion_distinta(): void
    {
        $usuario = $this->autenticar();
        $this->putJson('/api/me/password', $this->cambio(['password_confirmation' => 'OtraClave2026']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertSame($usuario->password, $usuario->fresh()->password);
    }

    public function test_exige_una_clave_diferente_de_la_actual(): void
    {
        $this->autenticar(['password' => 'Anterior123']);
        $this->putJson('/api/me/password', [
            'current_password' => 'Anterior123', 'password' => 'Anterior123', 'password_confirmation' => 'Anterior123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_ignora_identificadores_y_datos_ajenos_al_cambio_de_contrasena(): void
    {
        $usuario = $this->autenticar(['rol' => null]);
        $otro = User::factory()->create();
        $this->putJson('/api/me/password', $this->cambio([
            'id' => $otro->id, 'user_id' => $otro->id, 'email' => $otro->email, 'rol' => 'coordinadora',
        ]))->assertNoContent();

        $this->assertSame($otro->password, $otro->fresh()->password);
        $this->assertSame($usuario->email, $usuario->fresh()->email);
        $this->assertNull($usuario->fresh()->rol);
        $this->assertTrue(Hash::check($this->cambio()['password'], $usuario->fresh()->password));
    }

    public function test_no_acepta_la_contrasena_actual_de_otro_usuario(): void
    {
        $usuario = $this->autenticar();
        $otro = User::factory()->create(['password' => 'ClaveDeOtro123']);
        $this->putJson('/api/me/password', $this->cambio([
            'user_id' => $otro->id, 'current_password' => 'ClaveDeOtro123',
        ]))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertSame($usuario->password, $usuario->fresh()->password);
        $this->assertSame($otro->password, $otro->fresh()->password);
    }

    public function test_conserva_espacios_y_acepta_mayusculas_unicode(): void
    {
        $usuario = $this->autenticar();
        $clave = ' Árbolnuevo123 ';
        $this->putJson('/api/me/password', $this->cambio(['password' => $clave, 'password_confirmation' => $clave]))
            ->assertNoContent();
        $this->assertTrue(Hash::check($clave, $usuario->fresh()->password));
        $this->assertFalse(Hash::check(trim($clave), $usuario->fresh()->password));
    }

    public function test_limita_los_intentos_de_cambio_de_contrasena(): void
    {
        $this->autenticar();
        for ($intento = 0; $intento < 6; $intento++) {
            $this->putJson('/api/me/password', $this->cambio(['current_password' => 'Incorrecta123']))->assertUnprocessable();
        }
        $this->putJson('/api/me/password', $this->cambio())->assertTooManyRequests();
    }
}
