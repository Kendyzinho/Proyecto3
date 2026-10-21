<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// HU-05: crear, editar, desactivar y reactivar cuentas
class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();
        $this->secretaria = User::factory()->create(['name' => 'Marta Rojas', 'rol' => 'secretaria']);
    }

    private function crearEstudiante(string $nombre, string $cuerpoRut): User
    {
        $usuario = User::factory()->create([
            'name' => $nombre,
            'rut' => Rut::normalizar($cuerpoRut.Rut::digitoVerificador($cuerpoRut)),
            'email' => Str::slug($nombre, '.').'@alumnos.uta.cl',
            'rol' => null,
        ]);
        Estudiante::create(['user_id' => $usuario->id, 'anio_ingreso' => 2023]);

        return $usuario;
    }

    public function test_lista_las_cuentas_activas_primero_con_su_rol_y_estado(): void
    {
        $this->crearEstudiante('Camila Rojas Pizarro', '21334120');
        User::factory()->create(['name' => 'Diana Henry', 'rol' => 'coordinadora', 'fecha_baja' => '2026-03-01', 'motivo_baja' => 'Otro motivo']);

        $this->actingAs($this->secretaria)
            ->getJson('/api/usuarios')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Camila Rojas Pizarro')
            ->assertJsonPath('data.0.rol', 'estudiante')
            ->assertJsonPath('data.0.anio_ingreso', 2023)
            ->assertJsonPath('data.1.name', 'Marta Rojas')
            ->assertJsonPath('data.2.name', 'Diana Henry')
            ->assertJsonPath('data.2.activo', false);
    }

    public function test_crea_un_estudiante_con_rut_formateado_y_su_anio_de_ingreso(): void
    {
        $this->actingAs($this->secretaria)
            ->postJson('/api/usuarios', [
                'rol' => 'estudiante',
                'name' => 'Camila  Rojas Pizarro',
                'rut' => '21334120'.Rut::digitoVerificador('21334120'),
                'email' => 'Camila.Rojas@alumnos.uta.cl',
                'anio_ingreso' => 2023,
            ])
            ->assertCreated()
            ->assertJsonPath('data.rut', '21.334.120-'.Rut::digitoVerificador('21334120'))
            ->assertJsonPath('data.name', 'Camila Rojas Pizarro')
            ->assertJsonPath('data.email', 'camila.rojas@alumnos.uta.cl')
            ->assertJsonPath('data.rol', 'estudiante');

        $this->assertDatabaseHas('estudiantes', ['anio_ingreso' => 2023]);
    }

    public function test_rechaza_un_estudiante_con_rut_invalido_y_correo_no_institucional(): void
    {
        $this->actingAs($this->secretaria)
            ->postJson('/api/usuarios', [
                'rol' => 'estudiante',
                'name' => 'Tomás Flores',
                'rut' => '21.448.09-2',
                'email' => 'tomas.flores@gmail.com',
                'anio_ingreso' => 2025,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rut', 'email']);
    }

    public function test_crea_personal_sin_rut_ni_ficha_de_estudiante(): void
    {
        $this->actingAs($this->secretaria)
            ->postJson('/api/usuarios', ['rol' => 'coordinadora', 'name' => 'Diana Henry', 'rut' => null, 'email' => 'diana.henry@uta.cl'])
            ->assertCreated()
            ->assertJsonPath('data.rol', 'coordinadora')
            ->assertJsonPath('data.rut', null);

        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_edita_el_rol_del_personal(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora', 'email' => 'diana@uta.cl']);

        $this->actingAs($this->secretaria)
            ->putJson("/api/usuarios/{$coordinadora->id}", ['rol' => 'jefe', 'name' => 'Diana Henry', 'rut' => null, 'email' => 'diana@uta.cl'])
            ->assertOk()
            ->assertJsonPath('data.rol', 'jefe');
    }

    public function test_un_estudiante_no_puede_pasar_a_ser_personal(): void
    {
        $estudiante = $this->crearEstudiante('Camila Rojas Pizarro', '21334120');

        $this->actingAs($this->secretaria)
            ->putJson("/api/usuarios/{$estudiante->id}", [
                'rol' => 'coordinadora', 'name' => $estudiante->name, 'rut' => $estudiante->rut, 'email' => $estudiante->email,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rol');
    }

    public function test_desactivar_quita_el_acceso_y_cierra_sus_sesiones(): void
    {
        $estudiante = $this->crearEstudiante('Felipe Araya Soto', '19776203');
        $estudiante->createToken('web');

        $this->actingAs($this->secretaria)
            ->postJson("/api/usuarios/{$estudiante->id}/desactivar", ['motivo' => 'Egresado'])
            ->assertOk()
            ->assertJsonPath('data.activo', false)
            ->assertJsonPath('data.motivo_baja', 'Egresado');

        $this->assertSame(0, $estudiante->tokens()->count());
        $this->postJson('/api/login', ['email' => $estudiante->email, 'password' => 'password'])->assertForbidden();
    }

    public function test_la_secretaria_no_puede_desactivar_su_propia_cuenta(): void
    {
        $this->actingAs($this->secretaria)
            ->postJson("/api/usuarios/{$this->secretaria->id}/desactivar", ['motivo' => 'Otro motivo'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_reactivar_devuelve_el_acceso(): void
    {
        $egresado = User::factory()->create(['rol' => null, 'fecha_baja' => '2026-03-01', 'motivo_baja' => 'Egresado']);

        $this->actingAs($this->secretaria)
            ->postJson("/api/usuarios/{$egresado->id}/reactivar")
            ->assertOk()
            ->assertJsonPath('data.activo', true);

        $this->assertNull($egresado->fresh()->fecha_baja);
    }

    public function test_otros_roles_no_pueden_administrar_usuarios(): void
    {
        $coordinadora = User::factory()->create(['rol' => 'coordinadora']);

        $this->actingAs($coordinadora)->getJson('/api/usuarios')->assertForbidden();
    }
}
