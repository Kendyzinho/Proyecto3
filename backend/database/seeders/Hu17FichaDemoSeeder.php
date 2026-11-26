<?php

namespace Database\Seeders;

use App\Models\CentroPractica;
use App\Models\Documento;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\HistorialPractica;
use App\Models\Malla;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\RegistroDiario;
use App\Models\Supervisor;
use App\Models\TipoPractica;
use App\Models\User;
use App\Services\DocumentoService;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;

/**
 * Datos ficticios, repetibles y aislados para demostrar HU-17.
 * No se incluye en DatabaseSeeder: debe ejecutarse de forma explícita.
 */
class Hu17FichaDemoSeeder extends Seeder
{
    private const EMAIL_ESTUDIANTE = 'demo.hu17@alumnos.uta.cl';
    private const EMAIL_COORDINADORA = 'demo.hu17.coordinadora@uta.cl';
    private const PASSWORD = 'DemoHU2026!';
    private const RUT_ESTUDIANTE = '22345678';

    public function run(): void
    {
        if (! app()->environment('local')
            || ! in_array(DB::connection()->getDatabaseName(), ['practicas', 'practicas_test'], true)) {
            throw new LogicException('El seeder HU-17 solo se permite en APP_ENV=local y en practicas o practicas_test.');
        }

        foreach ([
            'users', 'estudiantes', 'periodos', 'tipos_practica', 'mallas', 'habilitaciones',
            'centros_practica', 'supervisores', 'practicas', 'historiales_practica',
            'registros_diarios', 'visitas_tecnicas', 'visita_practica', 'documentos', 'dictamenes',
        ] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                throw new RuntimeException("Falta la tabla {$tabla}. Ejecuta primero las migraciones y los seeders base.");
            }
        }

        DB::transaction(function (): void {
            $hoy = CarbonImmutable::today();
            $periodo = Periodo::actual() ?? Periodo::query()->firstOrCreate([
                'anio' => (int) $hoy->format('Y'),
                'semestre' => $hoy->month >= 7 ? 2 : 1,
            ]);
            $tipo = TipoPractica::query()
                ->where('categoria', 'laboral')
                ->whereNull('fecha_baja')
                ->whereHas('malla', fn ($q) => $q->whereNotNull('vigente_desde'))
                ->orderBy('id')
                ->first();

            if (! $tipo) {
                // La demo también funciona en una base migrada pero sin catálogo sembrado.
                $malla = Malla::query()->firstOrCreate(
                    ['nombre' => 'Malla de demostración HU-17'],
                    ['vigente_desde' => $hoy->subYear()->toDateString()],
                );
                if ($malla->vigente_desde === null || $malla->vigente_desde->isFuture()) {
                    $malla->update(['vigente_desde' => $hoy->subYear()->toDateString()]);
                }
                $tipo = TipoPractica::query()->firstOrCreate(
                    ['malla_id' => $malla->id, 'nombre' => 'Laboral I'],
                    ['categoria' => 'laboral', 'horas_requeridas' => 64, 'prerrequisito' => 'Dato de demostración'],
                );
            }

            $coordinadora = User::query()->updateOrCreate(
                ['email' => self::EMAIL_COORDINADORA],
                ['name' => 'Coordinadora Demo HU-17', 'password' => Hash::make(self::PASSWORD), 'rol' => 'coordinadora', 'fecha_baja' => null, 'motivo_baja' => null],
            );
            $usuario = User::query()->updateOrCreate(
                ['email' => self::EMAIL_ESTUDIANTE],
                [
                    'rut' => $this->rutDemoDisponible(),
                    'name' => 'Valentina Soto Riquelme (Demo HU-17)',
                    'password' => Hash::make(self::PASSWORD),
                    'rol' => null,
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                ],
            );
            $estudiante = Estudiante::query()->updateOrCreate(['user_id' => $usuario->id], ['anio_ingreso' => (int) $hoy->year - 2]);
            $habilitacion = Habilitacion::query()->updateOrCreate(
                ['estudiante_id' => $estudiante->id, 'tipo_practica_id' => $tipo->id, 'periodo_id' => $periodo->id],
                ['cumple_prerrequisito' => true, 'horas_requeridas' => $tipo->horas_requeridas],
            );

            $centro = CentroPractica::query()->updateOrCreate(
                ['rut' => '76.234.567-8'],
                [
                    'razon_social' => 'Centro de Innovación Audiovisual de Arica (Demo)',
                    'nombre' => 'Centro de Innovación Audiovisual',
                    'direccion' => 'Av. 18 de Septiembre 2222, Arica (dato ficticio)',
                    'telefono' => '+56 58 220 0000',
                    'es_interno' => true,
                    'fecha_alta' => $hoy->subYears(2)->toDateString(),
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                ],
            );
            $supervisor = Supervisor::query()->updateOrCreate(
                ['centro_id' => $centro->id, 'correo' => 'supervisor.hu17@example.test'],
                [
                    'nombre' => 'Paula Contreras (Demo)',
                    'telefono' => '+56 9 5555 0170',
                    'cargo' => 'Diseñadora multimedia',
                    'unidad' => 'Diseño digital',
                    'fecha_alta' => $hoy->subYears(2)->toDateString(),
                    'fecha_baja' => null,
                ],
            );

            $inicio = $hoy->subWeeks(3)->startOfWeek(CarbonImmutable::MONDAY);
            $fin = $hoy->addWeeks(6);
            $practica = Practica::query()->updateOrCreate(
                ['habilitacion_id' => $habilitacion->id],
                [
                    'supervisor_id' => $supervisor->id,
                    'origen' => 'asignacion',
                    'modalidad' => 'presencial',
                    'tareas' => 'Diseño de piezas digitales y apoyo en contenidos de comunicación audiovisual.',
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin' => $fin->toDateString(),
                ],
            );

            // Reinicia solo el historial de esta práctica de demostración.
            $practica->historiales()->delete();
            HistorialPractica::query()->create([
                'practica_id' => $practica->id,
                'user_id' => $usuario->id,
                'fecha_hora' => $inicio->setTime(8, 0),
                'estado_nuevo' => 'confirmada',
            ]);
            HistorialPractica::query()->create([
                'practica_id' => $practica->id,
                'user_id' => $coordinadora->id,
                'fecha_hora' => $inicio->setTime(8, 30),
                'estado_nuevo' => 'en_curso',
            ]);

            // Cuatro jornadas confirmadas suman 22 horas; la quinta rechazada no se computa.
            $jornadas = [
                [$inicio, '09:00', '15:00', 'Diseño de propuestas gráficas para una campaña informativa.', 'confirmado'],
                [$inicio->addDay(), '09:00', '16:00', 'Preparación de piezas para redes sociales y revisión de formatos.', 'confirmado'],
                [$inicio->addDays(2), '09:00', '15:00', 'Edición de material visual y coordinación con el equipo de comunicaciones.', 'confirmado'],
                [$inicio->addDays(3), '09:00', '12:00', 'Ajustes finales de diagramación para publicación digital.', 'confirmado'],
                [$inicio->addDays(4), '09:00', '17:00', 'Registro rechazado de muestra; estas horas no se suman al avance.', 'rechazado'],
            ];
            foreach ($jornadas as [$fecha, $entrada, $salida, $actividad, $validacion]) {
                RegistroDiario::query()->updateOrCreate(
                    ['practica_id' => $practica->id, 'fecha' => $fecha->toDateString()],
                    ['hora_entrada' => $entrada, 'hora_salida' => $salida, 'actividades' => $actividad, 'validacion' => $validacion],
                );
            }

            DB::table('visitas_tecnicas')->updateOrInsert(
                ['coordinadora_id' => $coordinadora->id, 'fecha_hora' => $hoy->subDays(5)->setTime(11, 0)->toDateTimeString(), 'modalidad' => 'virtual'],
                ['cancelada' => false, 'updated_at' => now(), 'created_at' => now()],
            );
            $visitaId = (int) DB::table('visitas_tecnicas')
                ->where('coordinadora_id', $coordinadora->id)
                ->where('fecha_hora', $hoy->subDays(5)->setTime(11, 0)->toDateTimeString())
                ->where('modalidad', 'virtual')
                ->value('id');
            DB::table('visita_practica')->insertOrIgnore([
                'visita_id' => $visitaId,
                'practica_id' => $practica->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // El dictamen queda pendiente mientras la práctica continúa en curso.
            // Genera carta y seguro reales usando el mismo servicio que HU-10.
            $documentos = app(DocumentoService::class);
            foreach (DocumentoService::TIPOS as $tipoDocumento) {
                if (! Documento::query()->where('practica_id', $practica->id)->where('tipo', $tipoDocumento)->exists()) {
                    $documentos->generar($practica, $tipoDocumento, $coordinadora);
                }
            }

            $this->command?->info('Datos HU-17 listos: '.self::EMAIL_ESTUDIANTE.' / '.self::PASSWORD);
            $this->command?->line('Ingresar como Coordinación: '.self::EMAIL_COORDINADORA.' / '.self::PASSWORD);
            $this->command?->line('Ficha: estudiante Valentina Soto Riquelme, práctica laboral en curso, 22 de 64 h confirmadas, una visita y dictamen pendiente.');
            $this->command?->warn('Los correos usan example.test; los datos son ficticios y no deben usarse para envíos reales.');
        });
    }

    /** Evita chocar con el RUT de la cuenta demo que creó HU-13 u otro usuario local. */
    private function rutDemoDisponible(): string
    {
        $cuerpo = (int) self::RUT_ESTUDIANTE;

        for ($intento = 0; $intento < 1000; $intento++, $cuerpo++) {
            $digitos = (string) $cuerpo;
            $rut = Rut::normalizar($digitos.Rut::digitoVerificador($digitos));
            $ocupadoPorOtroUsuario = User::query()
                ->where('rut', $rut)
                ->where('email', '!=', self::EMAIL_ESTUDIANTE)
                ->exists();

            if (! $ocupadoPorOtroUsuario) {
                return $rut;
            }
        }

        throw new RuntimeException('No se encontró un RUT de demostración disponible para HU-17.');
    }
}
