<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\CentroPractica;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// Cruces entre historias del Sprint 1: lo que cada HU pasa por sí sola, pero falla al combinarlas.
class CrucesSprint1Test extends TestCase
{
    use BaseDeDatosAislada;

    private User $coordinadora;

    private User $secretaria;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->coordinadora = User::where('email', 'coordinadora@uta.cl')->firstOrFail();
        $this->secretaria = User::where('email', 'secretaria@uta.cl')->firstOrFail();
        $this->periodo = Periodo::where('anio', 2026)->where('semestre', 2)->firstOrFail();
    }

    private function practicaDe(string $nombre): Practica
    {
        return Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', 'like', "{$nombre}%"))->firstOrFail();
    }

    private function habilitacionDe(string $nombre): Habilitacion
    {
        return Habilitacion::whereHas('estudiante.usuario', fn ($q) => $q->where('name', 'like', "{$nombre}%"))->firstOrFail();
    }

    private function supervisorActivo(string $nombre = 'Paula Contreras'): Supervisor
    {
        return Supervisor::where('nombre', $nombre)->firstOrFail();
    }

    private function datosExcepcional(Habilitacion $habilitacion, Supervisor $supervisor, string $modalidad = 'presencial'): array
    {
        return [
            'periodo_id' => $this->periodo->id, 'habilitacion_id' => $habilitacion->id, 'supervisor_id' => $supervisor->id,
            'modalidad' => $modalidad, 'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-12-10',
            'justificacion_excepcional' => 'Ingreso fuera de plazo.',
        ];
    }

    public function test_documentos_solo_lista_practicas_confirmadas(): void
    {
        $filas = collect($this->actingAs($this->secretaria)->getJson('/api/documentos?periodo_id='.$this->periodo->id)->assertOk()->json('data'));

        $this->assertNotContains('Ignacia Torres Bravo', $filas->pluck('estudiante'));
        $this->assertNotContains('Martín López Olivares', $filas->pluck('estudiante'));
        $this->assertContains('Josefa Ramírez Tapia', $filas->pluck('estudiante'));
    }

    public function test_no_se_generan_ni_envian_documentos_de_una_solicitud_sin_aprobar(): void
    {
        foreach (['Ignacia', 'Martín'] as $nombre) {
            $practica = $this->practicaDe($nombre);
            $this->actingAs($this->secretaria)->getJson("/api/practicas/{$practica->id}/documentos")->assertNotFound();
            $this->getJson("/api/practicas/{$practica->id}/documentos/carta")->assertNotFound();
            $this->postJson("/api/practicas/{$practica->id}/documentos/enviar")->assertNotFound();
        }
        $this->assertDatabaseCount('documentos', 0);
    }

    public function test_al_aprobar_la_solicitud_aparece_en_documentos(): void
    {
        $practica = $this->practicaDe('Ignacia');
        $this->actingAs($this->coordinadora)->postJson("/api/practicas/{$practica->id}/decision", ['decision' => 'aprobar'])->assertOk();

        $filas = collect($this->actingAs($this->secretaria)->getJson('/api/documentos?periodo_id='.$this->periodo->id)->json('data'));
        $this->assertContains('Ignacia Torres Bravo', $filas->pluck('estudiante'));
    }

    public function test_la_practica_excepcional_queda_confirmada_y_saca_al_estudiante_de_sin_centro(): void
    {
        $habilitacion = $this->habilitacionDe('Fernanda');
        $supervisor = $this->supervisorActivo();
        $this->actingAs($this->coordinadora);
        $this->assertContains($habilitacion->id, collect($this->getJson('/api/coordinacion/asignaciones/habilitaciones')->json('data'))->pluck('id'));

        $id = $this->postJson('/api/coordinacion/practicas-excepcionales', $this->datosExcepcional($habilitacion, $supervisor))->assertCreated()->json('data.id');

        $this->assertDatabaseHas('historiales_practica', ['practica_id' => $id, 'estado_nuevo' => 'confirmada', 'user_id' => $this->coordinadora->id]);
        $this->assertNotContains($habilitacion->id, collect($this->getJson('/api/coordinacion/asignaciones/habilitaciones')->json('data'))->pluck('id'));
        $this->postJson('/api/coordinacion/asignaciones', [
            'habilitacion_id' => $habilitacion->id, 'centro_id' => $supervisor->centro_id, 'supervisor_id' => $supervisor->id, 'modalidad' => 'presencial',
        ])->assertUnprocessable()->assertJsonValidationErrors('habilitacion_id');
    }

    public function test_la_practica_excepcional_descuenta_cupo_y_aparece_en_la_ficha_del_centro(): void
    {
        $habilitacion = $this->habilitacionDe('Fernanda');
        $supervisor = $this->supervisorActivo();
        $this->actingAs($this->coordinadora);
        $antes = collect($this->getJson("/api/centros-practica/{$supervisor->centro_id}")->json('data.requerimientos'))->firstWhere('categoria', 'laboral')['ocupados'];

        $this->postJson('/api/coordinacion/practicas-excepcionales', $this->datosExcepcional($habilitacion, $supervisor))->assertCreated();

        $ficha = $this->getJson("/api/centros-practica/{$supervisor->centro_id}")->json('data');
        $this->assertSame($antes + 1, collect($ficha['requerimientos'])->firstWhere('categoria', 'laboral')['ocupados']);
        $this->assertContains('Fernanda Quispe Apaza', collect($ficha['estudiantes'])->pluck('nombre'));
    }

    public function test_la_practica_excepcional_rechaza_supervisores_propuestos(): void
    {
        $supervisor = $this->supervisorActivo();
        $propuesto = Supervisor::create(['centro_id' => $supervisor->centro_id, 'nombre' => 'Propuesta Uno', 'correo' => 'propuesta@example.test']);
        $this->actingAs($this->coordinadora);

        $catalogo = $this->getJson('/api/coordinacion/practicas-excepcionales/catalogo?periodo_id='.$this->periodo->id)->json('supervisores');
        $this->assertNotContains($propuesto->id, collect($catalogo)->pluck('id'));
        $this->postJson('/api/coordinacion/practicas-excepcionales', $this->datosExcepcional($this->habilitacionDe('Fernanda'), $propuesto))
            ->assertUnprocessable()->assertJsonValidationErrors('supervisor_id');
    }

    public function test_la_practica_excepcional_admite_modalidad_a_distancia(): void
    {
        $this->actingAs($this->coordinadora)
            ->postJson('/api/coordinacion/practicas-excepcionales', $this->datosExcepcional($this->habilitacionDe('Fernanda'), $this->supervisorActivo(), 'a_distancia'))
            ->assertCreated();
    }

    public function test_la_metrica_no_cuenta_solicitudes_sin_aprobar(): void
    {
        $this->actingAs($this->coordinadora)->getJson('/api/coordinacion/metricas')->assertOk()->assertJsonPath('practicas_registradas', 5);
    }

    public function test_un_centro_propuesto_se_activa_al_subir_el_acuerdo_y_se_aprueba_su_supervisor(): void
    {
        $this->actingAs($this->coordinadora);
        $centroId = $this->postJson('/api/centros-practica', [
            'rut' => '76.086.428-5', 'razon_social' => 'Centro Nuevo SpA', 'nombre' => 'Centro Nuevo', 'es_interno' => false,
            'area_ids' => [Area::first()->id], 'supervisores' => [['nombre' => 'Sara Nueva', 'correo' => 'sara@example.test']],
        ])->assertCreated()->assertJsonPath('data.estado', 'propuesto')->json('data.id');
        $supervisor = Supervisor::where('centro_id', $centroId)->firstOrFail();

        $this->postJson("/api/centros-practica/{$centroId}/supervisores/{$supervisor->id}/activar")->assertUnprocessable();
        $this->post("/api/centros/{$centroId}/acuerdo", [
            'tipo' => 'tradicional', 'representante_legal' => 'Ana Legal', 'fecha_inicio' => today()->subDay()->toDateString(),
            'fecha_termino' => today()->addYear()->toDateString(), 'archivo' => UploadedFile::fake()->create('acuerdo.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('estado', 'vigente');
        $this->assertSame('activo', CentroPractica::findOrFail($centroId)->fresh()->fecha_alta ? 'activo' : 'propuesto');
        $this->postJson("/api/centros-practica/{$centroId}/supervisores/{$supervisor->id}/activar")->assertOk();
    }

    public function test_el_rut_del_centro_queda_en_un_solo_formato(): void
    {
        $this->actingAs($this->coordinadora)->postJson('/api/centros-practica', [
            'rut' => '76086428-5', 'razon_social' => 'Centro Nuevo SpA', 'nombre' => 'Centro Nuevo', 'es_interno' => false,
            'area_ids' => [Area::first()->id], 'supervisores' => [['nombre' => 'Sara', 'correo' => 'sara@example.test']],
        ])->assertCreated()->assertJsonPath('data.rut', '76.086.428-5');

        // Un centro ya registrado con puntos se reconoce aunque el RUT se escriba sin ellos
        $this->postJson('/api/centros-practica', [
            'rut' => '76543210-3', 'razon_social' => 'Duplicado', 'nombre' => 'Duplicado', 'es_interno' => false,
            'area_ids' => [Area::first()->id], 'supervisores' => [['nombre' => 'Sara', 'correo' => 'sara2@example.test']],
        ])->assertUnprocessable()->assertJsonValidationErrors('rut');
    }

    public function test_el_estudiante_no_duplica_un_centro_al_proponerlo_sin_puntos(): void
    {
        $this->actingAs(User::where('email', 'estudiante@alumnos.uta.cl')->firstOrFail());
        $this->postJson('/api/practicas/solicitud-profesional', $this->solicitud(['centro_rut' => '76543210-3']))
            ->assertUnprocessable()->assertJsonValidationErrors('centro_rut');
        $this->assertSame(1, CentroPractica::where('razon_social', 'like', 'Productora%')->count());
    }

    public function test_la_solicitud_rechaza_fechas_pasadas(): void
    {
        $this->actingAs(User::where('email', 'estudiante@alumnos.uta.cl')->firstOrFail());
        $this->postJson('/api/practicas/solicitud-profesional', $this->solicitud(['fecha_inicio' => '2020-03-01', 'fecha_termino' => '2020-06-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_la_api_responde_los_errores_en_espanol(): void
    {
        $respuesta = $this->actingAs($this->coordinadora)->postJson('/api/centros-practica', [
            'rut' => '76.086.428-5', 'razon_social' => 'X', 'nombre' => 'X', 'es_interno' => false,
            'area_ids' => [Area::first()->id], 'supervisores' => [['nombre' => 'Sara', 'correo' => 'no-es-correo']],
        ])->assertUnprocessable();

        $this->assertSame('El campo correo debe ser un correo válido.', $respuesta->json('errors')['supervisores.0.correo'][0]);
    }

    public function test_un_centro_nuevo_requiere_areas(): void
    {
        $this->actingAs($this->coordinadora)->postJson('/api/centros-practica', [
            'rut' => '76.086.428-5', 'razon_social' => 'X', 'nombre' => 'X', 'es_interno' => false,
            'supervisores' => [['nombre' => 'Sara', 'correo' => 'sara@example.test']],
        ])->assertUnprocessable()->assertJsonValidationErrors('area_ids');
    }

    public function test_la_nomina_carga_las_areas_y_hu08_encuentra_centros(): void
    {
        $csv = "RUT,Nombre,Correo,Año de ingreso,Práctica,Cumple prerrequisito,Áreas\n"
            ."21.678.901-6,Rocío Mendoza,rocio.mendoza@alumnos.uta.cl,2024,Laboral I,Sí,\"fotografía; Producción audiovisual\"\n";
        $this->actingAs($this->secretaria)->post('/api/nomina/confirmar', [
            'periodo_id' => $this->periodo->id, 'archivo' => UploadedFile::fake()->createWithContent('n.csv', $csv),
        ], ['Accept' => 'application/json'])->assertSuccessful();

        $habilitacion = $this->habilitacionDe('Rocío');
        $this->actingAs($this->coordinadora)->getJson("/api/coordinacion/asignaciones/habilitaciones/{$habilitacion->id}/centros")
            ->assertOk()->assertJsonFragment(['razon_social' => 'Ilustre Municipalidad de Arica']);
    }

    public function test_la_nomina_marca_un_area_inexistente_y_sigue_aceptando_la_planilla_sin_la_columna(): void
    {
        $conError = "RUT,Nombre,Correo,Año de ingreso,Práctica,Cumple prerrequisito,Áreas\n"
            ."21.678.901-6,Rocío Mendoza,rocio.mendoza@alumnos.uta.cl,2024,Laboral I,Sí,Cocina\n";
        $this->actingAs($this->secretaria)->post('/api/nomina/revisar', [
            'periodo_id' => $this->periodo->id, 'archivo' => UploadedFile::fake()->createWithContent('n.csv', $conError),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('filas.0.errores.areas', 'El área "Cocina" no existe');

        $sinColumna = "RUT,Nombre,Correo,Año de ingreso,Práctica,Cumple prerrequisito\n"
            ."21.678.901-6,Rocío Mendoza,rocio.mendoza@alumnos.uta.cl,2024,Laboral I,Sí\n";
        $this->post('/api/nomina/revisar', [
            'periodo_id' => $this->periodo->id, 'archivo' => UploadedFile::fake()->createWithContent('n.csv', $sinColumna),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('filas.0.resultado', 'habilitado');
    }

    public function test_agregar_un_estudiante_guarda_sus_areas(): void
    {
        $area = Area::where('nombre', 'Fotografía')->firstOrFail();
        $tipo = \App\Models\TipoPractica::where('nombre', 'Laboral I')->whereHas('malla', fn ($q) => $q->whereNotNull('vigente_desde'))->firstOrFail();
        $this->actingAs($this->secretaria)->postJson('/api/nomina/estudiantes', [
            'periodo_id' => $this->periodo->id, 'rut' => '21.678.901-6', 'nombre' => 'Rocío Mendoza', 'correo' => 'rocio.mendoza@alumnos.uta.cl',
            'anio_ingreso' => 2024, 'tipo_practica_id' => $tipo->id, 'cumple_prerrequisito' => true, 'area_ids' => [$area->id],
        ])->assertSuccessful();

        $estudiante = Estudiante::whereHas('usuario', fn ($q) => $q->where('email', 'rocio.mendoza@alumnos.uta.cl'))->firstOrFail();
        $this->assertDatabaseHas('estudiante_area', ['estudiante_id' => $estudiante->id, 'area_id' => $area->id]);
    }

    public function test_los_datos_de_demo_alcanzan_para_mostrar_todo_el_flujo(): void
    {
        $this->actingAs($this->coordinadora);
        $sinCentro = collect($this->getJson('/api/coordinacion/asignaciones/habilitaciones')->json('data'));
        $this->assertEqualsCanonicalizing(['Fernanda Quispe Apaza', 'Javier Mamani Condori'], $sinCentro->pluck('estudiante')->all());
        $this->assertNotEmpty($this->getJson("/api/coordinacion/asignaciones/habilitaciones/{$sinCentro[0]['id']}/centros")->json('data'));

        $bandeja = collect($this->getJson('/api/solicitudes-practica')->json('data'))->countBy('estado');
        $this->assertSame(2, $bandeja['Por revisar']);
        $this->assertSame(1, $bandeja['Devuelta']);

        $this->actingAs(User::where('email', 'estudiante@alumnos.uta.cl')->firstOrFail());
        $this->getJson('/api/practicas/solicitud-profesional')->assertOk()
            ->assertJsonPath('data.habilitacion.puede_postular', true)->assertJsonPath('data.solicitud', null);
    }

    private function solicitud(array $cambios = []): array
    {
        return [...[
            'accion' => 'enviar', 'centro_propuesto' => true, 'centro_id' => null, 'centro_razon_social' => 'Productora copia', 'centro_rut' => '76.086.428-5',
            'supervisor_propuesto' => true, 'supervisor' => ['nombre' => 'Sara', 'correo' => 'sara@example.test'],
            'modalidad' => 'Presencial', 'fecha_inicio' => today()->addWeek()->toDateString(), 'fecha_termino' => today()->addWeeks(9)->toDateString(),
            'tareas' => 'Diseño', 'horario' => ['dias' => ['Lun'], 'hora_desde' => '09:00', 'hora_hasta' => '13:00'],
        ], ...$cambios];
    }
}
