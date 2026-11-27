<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use App\Services\ConfirmacionSemanalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// HU-14: alertas tempranas. Hoy es el miércoles 18-11-2026; la práctica empezó el lunes 02-11
// con horario lunes, miércoles y viernes, así que los días esperados hasta ayer son
// 02, 04, 06, 09, 11, 13 y 16 de noviembre.
class AlertaTest extends TestCase
{
    use BaseDeDatosAislada;

    private const DIAS = ['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09', '2026-11-11', '2026-11-13', '2026-11-16'];

    private User $coordinadora;

    private Practica $practica;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(10, 0));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->coordinadora = User::where('email', 'coordinadora@uta.cl')->firstOrFail();

        // Una práctica confirmada del seeder con fechas y horario conocidos
        $this->practica = Practica::whereHas('habilitacion.estudiante.usuario', fn ($q) => $q->where('name', 'Diego Choque Vargas'))->firstOrFail();
        $this->practica->update(['fecha_inicio' => '2026-11-02', 'fecha_fin' => '2026-12-11']);
        $this->practica->horarios()->delete();
        foreach ([1, 3, 5] as $dia) {
            $this->practica->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '13:00', 'vigente_desde' => '2026-11-02']);
        }
    }

    private function marcar(array $dias, ?string $validacion = null): void
    {
        foreach ($dias as $dia) {
            DB::table('registros_diarios')->insert([
                'practica_id' => $this->practica->id, 'fecha' => $dia, 'hora_entrada' => '09:00', 'hora_salida' => '13:00',
                'validacion' => $validacion, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function confirmarSemanas(array $lunes): void
    {
        foreach ($lunes as $semana) {
            DB::table('confirmaciones_semanales')->insert([
                'practica_id' => $this->practica->id, 'semana' => $semana, 'supervisor_id' => $this->practica->supervisor_id,
                'fecha_hora_envio' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function generar(): void
    {
        $this->artisan('alertas:generar')->assertSuccessful();
    }

    private function alerta(string $tipo): ?Alerta
    {
        return Alerta::where('practica_id', $this->practica->id)->where('tipo', $tipo)->first();
    }

    public function test_tres_dias_esperados_seguidos_sin_marca_generan_la_alerta_roja(): void
    {
        $this->marcar(['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09']);

        $this->generar();

        $this->assertSame('2026-11-11', $this->alerta('abandono')?->fecha_referencia->toDateString());
    }

    public function test_dos_dias_sin_marca_no_generan_la_alerta_roja(): void
    {
        $this->marcar(['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09', '2026-11-11']);

        $this->generar();

        $this->assertNull($this->alerta('abandono'));
    }

    public function test_una_marca_rechazada_no_cuenta_como_valida(): void
    {
        $this->marcar(['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09']);
        $this->marcar(['2026-11-11'], 'rechazado');

        $this->generar();

        $this->assertSame('2026-11-11', $this->alerta('abandono')?->fecha_referencia->toDateString());
    }

    public function test_inasistencia_sin_justificar_cuando_vence_el_plazo_de_24_horas(): void
    {
        $this->marcar(['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09', '2026-11-11', '2026-11-13']);

        $this->generar();

        // El 16 venció ayer; el plazo del día de ayer (17) todavía corre
        $this->assertSame('2026-11-16', $this->alerta('inasistencia')?->fecha_referencia->toDateString());
        $this->assertNull($this->alerta('abandono'));
    }

    public function test_una_inasistencia_justificada_no_genera_alerta(): void
    {
        $this->marcar(['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09', '2026-11-11', '2026-11-13']);
        DB::table('registros_diarios')->insert([
            'practica_id' => $this->practica->id, 'fecha' => '2026-11-16', 'justificacion' => 'Control médico.',
            'fecha_justificacion' => '2026-11-16 18:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->generar();

        $this->assertNull($this->alerta('inasistencia'));
    }

    public function test_una_semana_sin_confirmar_por_mas_de_siete_dias_genera_la_alerta_amarilla(): void
    {
        $this->marcar(self::DIAS);

        $this->generar();

        // La semana del 02 terminó el 08: hoy ya pasaron más de 7 días. La del 09 todavía está en plazo.
        $this->assertSame('2026-11-02', $this->alerta('sin_confirmar')?->fecha_referencia->toDateString());
    }

    public function test_la_semana_confirmada_no_genera_alerta_amarilla(): void
    {
        $this->marcar(self::DIAS);
        $this->confirmarSemanas(['2026-11-02']);

        $this->generar();

        $this->assertNull($this->alerta('sin_confirmar'));
    }

    public function test_una_semana_sin_dias_registrados_no_genera_alerta_amarilla(): void
    {
        // Sin registros el supervisor no tiene nada que confirmar; la falta de marcas ya la cubre la roja
        $this->generar();

        $this->assertNull($this->alerta('sin_confirmar'));
        $this->assertNotNull($this->alerta('abandono'));
    }

    public function test_cierra_una_amarilla_antigua_de_una_semana_sin_dias_registrados(): void
    {
        $alerta = Alerta::create([
            'practica_id' => $this->practica->id, 'tipo' => 'sin_confirmar', 'fecha_referencia' => '2026-11-02', 'fecha_generada' => now(),
        ]);

        $this->generar();

        $this->assertNotNull($alerta->fresh()->fecha_resolucion);
    }

    public function test_al_correr_dos_veces_no_repite_alertas(): void
    {
        $this->generar();
        $total = Alerta::count();

        $this->generar();

        $this->assertSame($total, Alerta::count());
        $this->assertSame(1, Alerta::where('practica_id', $this->practica->id)->where('tipo', 'abandono')->count());
    }

    private function notificaciones(User $usuario): array
    {
        return $this->actingAs($usuario)->getJson('/api/notificaciones')->assertOk()->json();
    }

    private function estudiante(): User
    {
        return $this->practica->habilitacion->estudiante->usuario;
    }

    public function test_la_coordinadora_ve_una_linea_por_practica_con_la_mas_grave_primero(): void
    {
        $this->generar();
        $deDiego = Alerta::where('practica_id', $this->practica->id)->count();
        $this->assertGreaterThan(1, $deDiego);

        $respuesta = $this->notificaciones($this->coordinadora);

        $lineas = collect($respuesta['data'])->where('practica_id', $this->practica->id);
        $this->assertCount(1, $lineas);
        $this->assertSame('abandono', $lineas->first()['tipo']);
        $this->assertSame('Diego Choque Vargas', $lineas->first()['estudiante']);
        $this->assertSame($deDiego - 1, $lineas->first()['otras']);
        $this->assertNull($lineas->first()['enlace']);
        $this->assertSame(Alerta::distinct()->count('practica_id'), $respuesta['meta']['total']);
        $this->assertSame('abandono', $respuesta['data'][0]['tipo']);
    }

    public function test_el_jefe_ve_lo_mismo_que_la_coordinadora(): void
    {
        $this->generar();
        $jefe = User::where('rol', 'jefe')->firstOrFail();

        $this->assertSame($this->notificaciones($this->coordinadora), $this->notificaciones($jefe));
    }

    public function test_el_estudiante_ve_solo_sus_rojas_y_naranjas(): void
    {
        $this->generar();

        $tipos = collect($this->notificaciones($this->estudiante())['data'])->pluck('tipo');

        $this->assertContains('abandono', $tipos);
        $this->assertNotContains('sin_confirmar', $tipos);
        $otro = User::where('email', 'camila.rojas@alumnos.uta.cl')->firstOrFail();
        $this->assertSame([], collect($this->notificaciones($otro)['data'])->where('practica_id', $this->practica->id)->all());
    }

    public function test_el_supervisor_ve_las_amarillas_de_sus_practicas_con_el_enlace_para_confirmar(): void
    {
        $sergio = Supervisor::where('nombre', 'Sergio Paredes')->firstOrFail();
        $this->practica->update(['supervisor_id' => $sergio->id]);
        $this->marcar(self::DIAS);
        $this->generar();

        $lineas = collect($this->notificaciones($sergio->usuario)['data']);

        $this->assertSame(['sin_confirmar'], $lineas->pluck('tipo')->unique()->values()->all());
        $linea = $lineas->firstWhere('practica_id', $this->practica->id);
        $this->assertStringContainsString("/supervisor/asistencia/{$this->practica->id}/2026-11-02?", $linea['enlace']);
        $this->assertStringContainsString('signature=', $linea['enlace']);
        // Si cambia el supervisor de la práctica, el anterior deja de verla
        $this->practica->update(['supervisor_id' => Supervisor::where('nombre', 'Paula Contreras')->value('id')]);
        $this->assertNotContains($this->practica->id, collect($this->notificaciones($sergio->usuario)['data'])->pluck('practica_id'));
    }

    public function test_la_ficha_muestra_las_pendientes_y_el_historial(): void
    {
        $this->generar();
        $alerta = $this->alerta('abandono');

        $this->actingAs($this->coordinadora)->postJson("/api/coordinacion/alertas/{$alerta->id}/atender", ['nota' => '  Llamé al supervisor.  '])
            ->assertOk()
            ->assertJsonPath('data.nota', 'Llamé al supervisor.')
            ->assertJsonPath('data.atendida_por', $this->coordinadora->name);

        $ficha = $this->getJson("/api/coordinacion/practicas/{$this->practica->id}/alertas")->assertOk();
        $this->assertNotContains($alerta->id, collect($ficha->json('data.pendientes'))->pluck('id'));
        $this->assertSame($alerta->id, $ficha->json('data.historial.0.id'));
        $this->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")->assertStatus(422);

        $this->generar();
        $this->assertSame(1, Alerta::where('practica_id', $this->practica->id)->where('tipo', 'abandono')->count());
    }

    public function test_atendida_pero_sin_resolver_la_sigue_viendo_el_estudiante(): void
    {
        $this->generar();
        $alerta = $this->alerta('abandono');
        $this->actingAs($this->coordinadora)->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")->assertOk();

        $this->assertNotContains($this->practica->id, collect($this->notificaciones($this->coordinadora)['data'])->where('tipo', 'abandono')->pluck('practica_id'));
        $this->assertContains($alerta->id, collect($this->notificaciones($this->estudiante())['data'])->pluck('id'));
    }

    public function test_marcar_la_bitacora_resuelve_la_alerta_roja_en_el_momento(): void
    {
        $this->marcar(['2026-11-02', '2026-11-04', '2026-11-06', '2026-11-09']);
        $this->generar();
        $alerta = $this->alerta('abandono');

        $this->actingAs($this->estudiante())->putJson("/api/estudiante/practicas/{$this->practica->id}/bitacora/2026-11-16", [
            'tipo' => 'jornada', 'hora_entrada' => '09:00', 'hora_salida' => '13:00', 'actividades' => 'Diagramación del catálogo.',
        ])->assertOk();

        $this->assertNotNull($alerta->fresh()->fecha_resolucion);
        $this->assertNotContains($alerta->id, collect($this->notificaciones($this->estudiante())['data'])->pluck('id'));
        $this->assertNotContains($this->practica->id, collect($this->notificaciones($this->coordinadora)['data'])->where('tipo', 'abandono')->pluck('practica_id'));
        $this->actingAs($this->coordinadora)->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")->assertStatus(422);
    }

    public function test_confirmar_la_semana_resuelve_la_alerta_amarilla_en_el_momento(): void
    {
        $this->marcar(self::DIAS);
        $this->generar();
        $alerta = $this->alerta('sin_confirmar');
        $dias = DB::table('registros_diarios')->where('practica_id', $this->practica->id)->whereBetween('fecha', ['2026-11-02', '2026-11-08'])
            ->pluck('id')->map(fn ($id) => ['id' => $id, 'validacion' => 'confirmado'])->all();

        app(ConfirmacionSemanalService::class)->confirmar($this->practica, Carbon::parse('2026-11-02'), ['dias' => $dias]);

        $this->assertNotNull($alerta->fresh()->fecha_resolucion);
        $this->assertSame('2026-11-02', $alerta->fecha_referencia->toDateString());
    }

    public function test_la_revision_de_la_manana_resuelve_lo_que_ya_se_puso_al_dia(): void
    {
        $this->marcar(self::DIAS);
        $this->generar();
        $this->confirmarSemanas(['2026-11-02']);

        $this->generar();

        $this->assertNotNull($this->alerta('sin_confirmar')->fecha_resolucion);
    }

    public function test_otros_roles_no_ven_ni_atienden_las_alertas_de_la_ficha(): void
    {
        $this->generar();
        $alerta = Alerta::firstOrFail();
        $secretaria = User::where('email', 'secretaria@uta.cl')->firstOrFail();

        $this->assertSame([], $this->notificaciones($secretaria)['data']);
        $this->getJson("/api/coordinacion/practicas/{$this->practica->id}/alertas")->assertForbidden();
        $this->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")->assertForbidden();
    }
}
