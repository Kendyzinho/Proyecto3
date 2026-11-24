<?php

namespace Tests\Feature;

use App\Models\{User, Estudiante, Habilitacion, Practica, TipoPractica, Periodo};
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PortafolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mallas, tipos de práctica y períodos: el test no depende de lo que haya quedado en la base
        $this->seed(CatalogoSeeder::class);
    }

    public function test_portafolio_propio_validacion_actualizacion_y_permisos(): void
    {
        $usuario = User::factory()->create(['rol' => null]);
        $estudiante = Estudiante::create(['user_id' => $usuario->id, 'anio_ingreso' => 2024]);
        $habilitacion = Habilitacion::create(['estudiante_id' => $estudiante->id,
            'tipo_practica_id' => TipoPractica::firstOrFail()->id, 'periodo_id' => Periodo::firstOrFail()->id,
            'horas_requeridas' => 64, 'cumple_prerrequisito' => true]);
        $practica = Practica::create(['habilitacion_id' => $habilitacion->id, 'origen' => 'solicitud', 'modalidad' => 'presencial']);
        $url = "/api/estudiante/practicas/{$practica->id}/portafolio";
        $this->getJson('/api/estudiante/portafolios')->assertUnauthorized();
        $this->actingAs($usuario)->getJson('/api/estudiante/portafolios')->assertOk()->assertJsonCount(1, 'data');
        foreach (['incorrecta', 'javascript:alert(1)', 'ftp://example.org/a', str_repeat('a', 256)] as $invalida) {
            $this->putJson($url, ['url' => $invalida])->assertUnprocessable();
        }
        $this->putJson($url, ['url' => 'https://example.org/portafolio', 'archivo' => UploadedFile::fake()->create('archivo.pdf')])->assertUnprocessable();
        $this->putJson($url, ['url' => 'https://example.org/portafolio'])->assertOk();
        $this->putJson($url, ['url' => 'https://example.org/actualizado'])->assertOk();
        $this->assertSame(1, $practica->documentos()->where('tipo', 'portafolio')->count());
        $this->getJson('/api/estudiante/portafolios')->assertJsonPath('data.0.url', 'https://example.org/actualizado');
        $this->actingAs(User::factory()->create(['rol' => null]))->putJson($url, ['url' => 'https://example.org/ajeno'])->assertNotFound();
        $this->actingAs(User::factory()->create(['rol' => 'secretaria']))->getJson('/api/estudiante/portafolios')->assertForbidden();
    }
}
