<?php

namespace Tests\Feature;

use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\User;
use App\Support\Rut;
use Database\Seeders\CatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

// HU-07: carga de la nómina y verificación de prerrequisitos
class NominaTest extends TestCase
{
    use RefreshDatabase;

    private const ENCABEZADO = "RUT,Nombre,Correo,Año de ingreso,Práctica,Cumple prerrequisito\n";

    private User $secretaria;

    private Periodo $periodo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogoSeeder::class);
        Storage::fake();
        $this->secretaria = User::factory()->create(['rol' => 'secretaria']);
        $this->periodo = Periodo::where('anio', 2026)->where('semestre', 2)->first();
    }

    // RUT válido a partir de su cuerpo
    private function rut(string $cuerpo): string
    {
        return $cuerpo.'-'.Rut::digitoVerificador($cuerpo);
    }

    private function csv(string $filas, string $nombre = 'nomina.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, self::ENCABEZADO.$filas);
    }

    private function planillaDeEjemplo(): UploadedFile
    {
        return $this->csv(
            $this->rut('21556302').",Ignacia Muñoz Vega,ignacia.munoz@alumnos.uta.cl,2025,Laboral I,Sí\n".
            $this->rut('21902330').",Benjamín Castro Ríos,benjamin.castro@alumnos.uta.cl,2024,Laboral II,No\n".
            "21.448.09-2,Tomás Flores Gutiérrez,tomas.flores@gmail.com,2025,Laboral I,Sí\n"
        );
    }

    public function test_revisa_la_planilla_y_clasifica_cada_fila_sin_guardar(): void
    {
        $respuesta = $this->actingAs($this->secretaria)
            ->post('/api/nomina/revisar', ['archivo' => $this->planillaDeEjemplo(), 'periodo_id' => $this->periodo->id])
            ->assertOk()
            ->assertJsonPath('resumen', ['total' => 3, 'habilitados' => 1, 'bloqueados' => 1, 'con_error' => 1])
            ->assertJsonPath('filas.0.resultado', 'habilitado')
            ->assertJsonPath('filas.0.rut', '21.556.302-2')
            ->assertJsonPath('filas.0.prerrequisito', 'Primer año aprobado')
            ->assertJsonPath('filas.1.resultado', 'bloqueado')
            ->assertJsonPath('filas.2.resultado', 'error');

        $this->assertSame(['rut' => 'RUT inválido', 'correo' => 'Correo no institucional'], $respuesta->json('filas.2.errores'));
        $this->assertSame('21.448.09-2', $respuesta->json('filas.2.rut')); // se devuelve tal como venía
        $this->assertDatabaseCount('habilitaciones', 0);
        $this->assertDatabaseCount('cargas_nomina', 0);
    }

    public function test_confirmar_guarda_habilitados_y_bloqueados_pero_no_las_filas_con_error(): void
    {
        $this->actingAs($this->secretaria)
            ->post('/api/nomina/confirmar', ['archivo' => $this->planillaDeEjemplo(), 'periodo_id' => $this->periodo->id])
            ->assertCreated()
            ->assertJsonPath('habilitados', 1)
            ->assertJsonPath('bloqueados', 1)
            ->assertJsonPath('con_error', 1);

        $this->assertDatabaseHas('cargas_nomina', ['secretaria_id' => $this->secretaria->id, 'filas_con_error' => 1]);
        $this->assertDatabaseHas('users', ['rut' => '21.556.302-2', 'email' => 'ignacia.munoz@alumnos.uta.cl', 'rol' => null]);
        $this->assertDatabaseMissing('users', ['email' => 'tomas.flores@gmail.com']);
        $this->assertSame(2, Habilitacion::whereNotNull('carga_id')->count());

        $ignacia = Habilitacion::whereHas('estudiante.usuario', fn ($q) => $q->where('rut', '21.556.302-2'))->first();
        $this->assertTrue($ignacia->cumple_prerrequisito);
        $this->assertSame(64, $ignacia->horas_requeridas); // copiadas del tipo Laboral I
        $this->assertCount(1, Storage::files('nominas'));
    }

    public function test_no_habilita_dos_veces_al_mismo_estudiante_en_el_periodo(): void
    {
        $this->actingAs($this->secretaria)
            ->post('/api/nomina/confirmar', ['archivo' => $this->planillaDeEjemplo(), 'periodo_id' => $this->periodo->id]);

        $this->actingAs($this->secretaria)
            ->post('/api/nomina/revisar', ['archivo' => $this->planillaDeEjemplo(), 'periodo_id' => $this->periodo->id])
            ->assertOk()
            ->assertJsonPath('filas.0.resultado', 'error')
            ->assertJsonPath('filas.0.errores.rut', 'Ya está en la nómina de este período');
    }

    public function test_marca_el_rut_repetido_dentro_de_la_planilla(): void
    {
        $rut = $this->rut('21556302');
        $archivo = $this->csv(
            "{$rut},Ignacia Muñoz Vega,ignacia.munoz@alumnos.uta.cl,2025,Laboral I,Sí\n".
            "{$rut},Ignacia Muñoz Vega,ignacia.munoz@alumnos.uta.cl,2025,Laboral I,Sí\n"
        );

        $this->actingAs($this->secretaria)
            ->post('/api/nomina/revisar', ['archivo' => $archivo, 'periodo_id' => $this->periodo->id])
            ->assertJsonPath('filas.0.resultado', 'habilitado')
            ->assertJsonPath('filas.1.errores.rut', 'RUT repetido en la planilla (fila 2)');
    }

    public function test_rechaza_una_practica_que_no_es_de_la_malla_vigente(): void
    {
        // "Laboral" es de la malla nueva, que todavía está en preparación
        $archivo = $this->csv($this->rut('21556302').",Ignacia Muñoz Vega,ignacia.munoz@alumnos.uta.cl,2025,Laboral,Sí\n");

        $this->actingAs($this->secretaria)
            ->post('/api/nomina/revisar', ['archivo' => $archivo, 'periodo_id' => $this->periodo->id])
            ->assertJsonPath('filas.0.errores.practica', 'La práctica no existe en la malla vigente');
    }

    public function test_lee_el_csv_de_excel_en_espanol_con_punto_y_coma(): void
    {
        $contenido = mb_convert_encoding(
            "RUT;Nombre;Correo;Año de ingreso;Práctica;Cumple prerrequisito\n".
            $this->rut('21556302').";Ignacia Muñoz Vega;ignacia.munoz@alumnos.uta.cl;2025;Laboral I;Si\n",
            'Windows-1252', 'UTF-8'
        );
        $archivo = UploadedFile::fake()->createWithContent('nomina.csv', $contenido);

        $this->actingAs($this->secretaria)
            ->post('/api/nomina/revisar', ['archivo' => $archivo, 'periodo_id' => $this->periodo->id])
            ->assertOk()
            ->assertJsonPath('filas.0.resultado', 'habilitado')
            ->assertJsonPath('filas.0.nombre', 'Ignacia Muñoz Vega');
    }

    public function test_lee_una_planilla_excel(): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'nomina').'.xlsx';
        $escritor = new Writer;
        $escritor->openToFile($ruta);
        $escritor->addRow(Row::fromValues(['RUT', 'Nombre', 'Correo', 'Año de ingreso', 'Práctica', 'Cumple prerrequisito']));
        $escritor->addRow(Row::fromValues([$this->rut('21556302'), 'Ignacia Muñoz Vega', 'ignacia.munoz@alumnos.uta.cl', 2025, 'Laboral I', 'Sí']));
        $escritor->close();
        $archivo = new UploadedFile($ruta, 'nomina.xlsx', null, null, true);

        $this->actingAs($this->secretaria)
            ->post('/api/nomina/revisar', ['archivo' => $archivo, 'periodo_id' => $this->periodo->id])
            ->assertOk()
            ->assertJsonPath('filas.0.resultado', 'habilitado')
            ->assertJsonPath('filas.0.anio_ingreso', 2025);
    }

    public function test_avisa_si_falta_una_columna(): void
    {
        $archivo = UploadedFile::fake()->createWithContent('nomina.csv', "RUT,Nombre,Correo\n1-9,Ana,ana@alumnos.uta.cl\n");

        $this->actingAs($this->secretaria)
            ->postJson('/api/nomina/revisar', ['archivo' => $archivo, 'periodo_id' => $this->periodo->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.archivo.0', 'La planilla no tiene la columna "Año de ingreso". Usa la plantilla.');
    }

    public function test_rechaza_archivos_que_no_son_excel_ni_csv(): void
    {
        $this->actingAs($this->secretaria)
            ->postJson('/api/nomina/revisar', ['archivo' => UploadedFile::fake()->create('nomina.pdf', 10), 'periodo_id' => $this->periodo->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_agrega_un_estudiante_individual_sin_carga(): void
    {
        $catalogo = $this->actingAs($this->secretaria)->getJson('/api/nomina/catalogo')->assertOk();
        $laboralI = collect($catalogo->json('practicas'))->firstWhere('nombre', 'Laboral I');

        $this->actingAs($this->secretaria)->postJson('/api/nomina/estudiantes', [
            'periodo_id' => $this->periodo->id,
            'rut' => '21556302-2',
            'nombre' => 'Ignacia Muñoz Vega',
            'correo' => 'Ignacia.Munoz@alumnos.uta.cl',
            'anio_ingreso' => 2025,
            'tipo_practica_id' => $laboralI['id'],
            'cumple_prerrequisito' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['rut' => '21.556.302-2', 'email' => 'ignacia.munoz@alumnos.uta.cl']);
        $this->assertDatabaseHas('habilitaciones', ['carga_id' => null, 'tipo_practica_id' => $laboralI['id']]);

        $this->actingAs($this->secretaria)->getJson('/api/nomina?periodo_id='.$this->periodo->id)
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Ignacia Muñoz Vega')
            ->assertJsonPath('data.0.origen', 'manual');
    }

    public function test_al_agregar_un_estudiante_informa_el_error_en_su_campo(): void
    {
        $this->actingAs($this->secretaria)->postJson('/api/nomina/estudiantes', [
            'periodo_id' => $this->periodo->id,
            'rut' => '21556302-2',
            'nombre' => 'Ignacia Muñoz Vega',
            'correo' => 'ignacia@gmail.com',
            'anio_ingreso' => 2025,
            'tipo_practica_id' => 999,
            'cumple_prerrequisito' => true,
        ])->assertStatus(422)
            ->assertJsonPath('errors.correo.0', 'Correo no institucional')
            ->assertJsonPath('errors.tipo_practica_id.0', 'Falta la práctica');
    }

    public function test_descarga_la_plantilla(): void
    {
        $this->actingAs($this->secretaria)->get('/api/nomina/plantilla')
            ->assertOk()
            ->assertDownload('plantilla_nomina.xlsx');
    }

    public function test_solo_la_secretaria_puede_cargar_la_nomina(): void
    {
        $this->getJson('/api/nomina/catalogo')->assertUnauthorized();

        foreach (['coordinadora', 'jefe', null] as $rol) {
            $this->actingAs(User::factory()->create(['rol' => $rol]))
                ->getJson('/api/nomina/catalogo')
                ->assertForbidden();
        }
    }

    public function test_el_rut_se_valida_con_su_digito_verificador(): void
    {
        $this->assertSame('21.556.302-2', Rut::normalizar('21556302-2'));
        $this->assertSame('21.556.302-2', Rut::normalizar(' 21.556.302-2 '));
        $this->assertNull(Rut::normalizar('21.556.302-3'));
        $this->assertNull(Rut::normalizar('21.448.09-2'));
        $this->assertSame('20.998.413-K', Rut::normalizar('20998413k'));
    }
}
