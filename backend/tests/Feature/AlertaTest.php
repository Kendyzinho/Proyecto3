<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Practica;
use App\Models\User;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
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

    public function test_al_correr_dos_veces_no_repite_alertas(): void
    {
        $this->generar();
        $total = Alerta::count();

        $this->generar();

        $this->assertSame($total, Alerta::count());
        $this->assertSame(1, Alerta::where('practica_id', $this->practica->id)->where('tipo', 'abandono')->count());
    }

    public function test_la_coordinadora_ve_las_alertas_abiertas_primero_las_rojas(): void
    {
        $this->generar();

        $respuesta = $this->actingAs($this->coordinadora)->getJson('/api/coordinacion/alertas')->assertOk();

        $this->assertSame('abandono', $respuesta->json('data.0.tipo'));
        $this->assertSame(Alerta::count(), $respuesta->json('meta.abiertas'));
        $fila = collect($respuesta->json('data'))->firstWhere('practica.id', $this->practica->id);
        $this->assertSame('Diego Choque Vargas', $fila['practica']['estudiante']);
        $this->assertFalse($fila['atendida']);
    }

    public function test_marcar_como_atendida_la_saca_de_las_abiertas_y_no_vuelve(): void
    {
        $this->generar();
        $alerta = $this->alerta('abandono');

        $this->actingAs($this->coordinadora)->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")
            ->assertOk()
            ->assertJsonPath('data.atendida', true)
            ->assertJsonPath('data.atendida_por', $this->coordinadora->name);

        $this->generar();

        $this->assertSame(1, Alerta::where('practica_id', $this->practica->id)->where('tipo', 'abandono')->count());
        $atendidas = $this->getJson('/api/coordinacion/alertas?estado=atendidas')->assertOk();
        $this->assertSame([$alerta->id], collect($atendidas->json('data'))->pluck('id')->all());
        $this->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")->assertStatus(422);
    }

    public function test_otros_roles_no_ven_ni_atienden_alertas(): void
    {
        $this->generar();
        $alerta = Alerta::firstOrFail();
        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail());

        $this->getJson('/api/coordinacion/alertas')->assertForbidden();
        $this->postJson("/api/coordinacion/alertas/{$alerta->id}/atender")->assertForbidden();
    }
}
