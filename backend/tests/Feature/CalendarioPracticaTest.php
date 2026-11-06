<?php

namespace Tests\Feature;

use App\Models\Habilitacion;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\DocumentoService;
use Database\Seeders\CatalogoSeeder;
use Database\Seeders\CentrosDemoSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\UsuarioSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BaseDeDatosAislada;
use Tests\TestCase;

// Fechas y horario (Art. 17): HU-08 y HU-11 no los definen al crear la práctica; HU-14 los necesita para saber qué días se espera marca.
class CalendarioPracticaTest extends TestCase
{
    use BaseDeDatosAislada;

    private User $coordinadora;

    private array $datos = [
        'fecha_inicio' => '2026-11-16', 'fecha_fin' => '2026-12-18',
        'dias' => [1, 3, 5], 'hora_desde' => '09:00', 'hora_hasta' => '13:00',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0));
        $this->seed([CatalogoSeeder::class, CentrosDemoSeeder::class, UsuarioSeeder::class, DemoSeeder::class]);
        $this->coordinadora = User::where('email', 'coordinadora@uta.cl')->firstOrFail();
        $this->actingAs($this->coordinadora);
    }

    // Asigna por HU-08 al primer estudiante pendiente, en el primer centro disponible
    private function asignada(): Practica
    {
        $habilitacion = $this->getJson('/api/coordinacion/asignaciones/habilitaciones')->json('data.0');
        $centro = $this->getJson("/api/coordinacion/asignaciones/habilitaciones/{$habilitacion['id']}/centros")->json('data.0');
        $id = $this->postJson('/api/coordinacion/asignaciones', [
            'habilitacion_id' => $habilitacion['id'], 'centro_id' => $centro['id'],
            'supervisor_id' => $centro['supervisores'][0]['id'], 'modalidad' => 'presencial',
        ])->assertCreated()->json('data.id');

        return Practica::findOrFail($id);
    }

    private function fila(Practica $practica): ?array
    {
        return collect($this->getJson('/api/coordinacion/calendarios')->assertOk()->json('data'))->firstWhere('id', $practica->id);
    }

    public function test_solo_la_coordinadora_gestiona_fechas_y_horario(): void
    {
        $practica = $this->asignada();
        $this->actingAs(User::where('email', 'secretaria@uta.cl')->firstOrFail());

        $this->getJson('/api/coordinacion/calendarios')->assertForbidden();
        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", $this->datos)->assertForbidden();
    }

    public function test_la_practica_asignada_aparece_sin_fechas_ni_horario(): void
    {
        $fila = $this->fila($this->asignada());

        $this->assertNull($fila['fecha_inicio']);
        $this->assertNull($fila['fecha_fin']);
        $this->assertNull($fila['horario']);
        $this->assertNotEmpty($fila['estudiante']);
        $this->assertNotEmpty($fila['centro']);
    }

    public function test_la_practica_excepcional_aparece_con_sus_fechas_y_sin_horario(): void
    {
        $habilitacion = Habilitacion::whereHas('estudiante.usuario', fn ($q) => $q->where('name', 'like', 'Fernanda%'))->firstOrFail();
        $id = $this->postJson('/api/coordinacion/practicas-excepcionales', [
            'periodo_id' => $habilitacion->periodo_id, 'habilitacion_id' => $habilitacion->id,
            'supervisor_id' => Supervisor::where('nombre', 'Paula Contreras')->firstOrFail()->id, 'modalidad' => 'presencial',
            'fecha_inicio' => '2026-11-10', 'fecha_fin' => '2026-12-10', 'justificacion_excepcional' => 'Ingreso fuera de plazo.',
        ])->assertCreated()->json('data.id');

        $fila = $this->fila(Practica::findOrFail($id));

        $this->assertSame('2026-11-10', $fila['fecha_inicio']);
        $this->assertNull($fila['horario']);
    }

    public function test_guarda_fechas_y_horario_y_la_carta_incluye_el_periodo(): void
    {
        $practica = $this->asignada();

        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", $this->datos)->assertOk();

        $fila = $this->fila($practica);
        $this->assertSame('2026-11-16', $fila['fecha_inicio']);
        $this->assertSame('2026-12-18', $fila['fecha_fin']);
        $this->assertSame(['dias' => [1, 3, 5], 'hora_desde' => '09:00', 'hora_hasta' => '13:00'], $fila['horario']);
        $this->assertStringContainsString('entre el', app(DocumentoService::class)->contenidoCarta($practica->fresh())['parrafos'][0]);
    }

    public function test_valida_fechas_dias_y_horas(): void
    {
        $practica = $this->asignada();
        $url = "/api/coordinacion/calendarios/{$practica->id}";

        $this->putJson($url, ['fecha_fin' => '2026-11-01'] + $this->datos)->assertJsonValidationErrors('fecha_fin');
        $this->putJson($url, ['dias' => []] + $this->datos)->assertJsonValidationErrors('dias');
        $this->putJson($url, ['dias' => [8]] + $this->datos)->assertJsonValidationErrors('dias.0');
        $this->putJson($url, ['dias' => [1, 1]] + $this->datos)->assertJsonValidationErrors('dias.0');
        $this->putJson($url, ['hora_hasta' => '08:00'] + $this->datos)->assertJsonValidationErrors('hora_hasta');
        $this->assertSame(0, $practica->horarios()->count());
    }

    public function test_no_aplica_a_una_solicitud_sin_aprobar(): void
    {
        $porRevisar = Practica::conEstado(['por_revisar'])->firstOrFail();

        $this->putJson("/api/coordinacion/calendarios/{$porRevisar->id}", $this->datos)->assertNotFound();
        $this->assertNull($this->fila($porRevisar));
    }

    public function test_antes_de_partir_el_cambio_reemplaza_el_horario(): void
    {
        $practica = $this->asignada();
        $url = "/api/coordinacion/calendarios/{$practica->id}";
        $this->putJson($url, $this->datos)->assertOk();

        $this->putJson($url, ['dias' => [2]] + $this->datos)->assertOk();

        $this->assertSame(1, $practica->horarios()->count());
        $this->assertSame([2], $this->fila($practica)['horario']['dias']);
    }

    public function test_una_vez_iniciada_el_cambio_conserva_el_horario_anterior(): void
    {
        $practica = $this->asignada();
        $url = "/api/coordinacion/calendarios/{$practica->id}";
        $this->putJson($url, $this->datos)->assertOk();
        $this->travelTo(now()->setDate(2026, 11, 25));

        $this->putJson($url, ['dias' => [2]] + $this->datos)->assertOk();

        // Lo anterior sirve para saber qué días se esperaba marca hasta el cambio
        $this->assertSame(3, $practica->horarios()->whereDate('vigente_desde', '2026-11-16')->count());
        $this->assertSame(1, $practica->horarios()->whereDate('vigente_desde', '2026-11-25')->count());
        $this->assertSame([2], $this->fila($practica)['horario']['dias']);
    }

    public function test_la_bandeja_de_solicitudes_solo_muestra_el_horario_vigente(): void
    {
        $practica = Practica::conEstado(['confirmada'])->where('origen', 'solicitud')->firstOrFail();
        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", $this->datos)->assertOk();
        $this->travelTo(now()->setDate(2026, 11, 25));
        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", ['dias' => [2]] + $this->datos)->assertOk();

        $fila = collect($this->getJson('/api/solicitudes-practica')->assertOk()->json('data'))->firstWhere('id', $practica->id);

        $this->assertSame(['Mar'], $fila['horario']['dias']);
    }

    public function test_cambiar_las_fechas_deja_vieja_la_carta_ya_generada(): void
    {
        $practica = $this->asignada();
        $servicio = app(DocumentoService::class);
        $carta = $servicio->generar($practica, 'carta', $this->coordinadora);
        $carta->update(['fecha_envio' => now()]);
        $this->travel(5)->minutes();

        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", $this->datos)->assertOk();

        $nueva = $servicio->vigente($practica->fresh(), 'carta', $this->coordinadora);
        $this->assertNotSame($carta->id, $nueva->id);
        $this->assertNull($nueva->fecha_envio);
    }

    public function test_cambiar_solo_el_horario_conserva_la_carta(): void
    {
        $practica = $this->asignada();
        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", $this->datos)->assertOk();
        $servicio = app(DocumentoService::class);
        $this->travel(1)->minutes();
        $carta = $servicio->generar($practica->fresh(), 'carta', $this->coordinadora);
        $this->travel(5)->minutes();

        $this->putJson("/api/coordinacion/calendarios/{$practica->id}", ['dias' => [2]] + $this->datos)->assertOk();

        $this->assertSame($carta->id, $servicio->vigente($practica->fresh(), 'carta', $this->coordinadora)->id);
    }
}
