<?php

namespace Database\Seeders;

use App\Models\AcuerdoColaboracion;
use App\Models\CentroPractica;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\HistorialPractica;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\RegistroDiario;
use App\Models\Supervisor;
use App\Models\TipoPractica;
use App\Models\User;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;

/**
 * Crea una cuenta y una práctica en curso con bitácora de muestra para HU-13.
 * Ejecutar explícitamente en local: php artisan db:seed --class=Hu13BitacoraDemoSeeder
 */
class Hu13BitacoraDemoSeeder extends Seeder
{
    private const EMAIL = 'demo.hu13@alumnos.uta.cl';
    private const PASSWORD = 'DemoHU2026!';
    private const RUT_CUERPO = '22345678';

    public function run(): void
    {
        $base = DB::connection()->getDatabaseName();
        if (! app()->environment('local') || ! in_array($base, ['practicas', 'practicas_test'], true)) {
            throw new LogicException('Este seeder demo solo puede ejecutarse con APP_ENV=local y la base practicas o practicas_test.');
        }

        foreach ([
            'users', 'estudiantes', 'periodos', 'mallas', 'tipos_practica', 'habilitaciones',
            'centros_practica', 'supervisores', 'acuerdos_colaboracion', 'practicas',
            'historiales_practica', 'horarios', 'registros_diarios',
        ] as $tabla) {
            if (! Schema::hasTable($tabla)) {
                throw new RuntimeException("Falta la tabla {$tabla}. Ejecuta php artisan migrate primero.");
            }
        }

        DB::transaction(function (): void {
            $hoy = CarbonImmutable::today();
            $inicio = $hoy->subWeeks(5)->startOfWeek(CarbonImmutable::MONDAY);
            $termino = $hoy->addWeeks(6);
            $periodo = Periodo::actual() ?? Periodo::firstOrCreate([
                'anio' => (int) $hoy->format('Y'),
                'semestre' => $hoy->month >= 7 ? 2 : 1,
            ]);
            $tipo = TipoPractica::query()
                ->where('categoria', 'laboral')->whereNull('fecha_baja')
                ->whereHas('malla', fn ($query) => $query->whereNotNull('vigente_desde'))
                ->orderBy('id')->first();

            if (! $tipo) {
                throw new RuntimeException('No existe un tipo laboral vigente. Ejecuta primero los seeders de catálogo.');
            }

            $usuario = User::query()->updateOrCreate(
                ['email' => self::EMAIL],
                [
                    'rut' => Rut::normalizar(self::RUT_CUERPO.Rut::digitoVerificador(self::RUT_CUERPO)),
                    'name' => 'Valentina Soto Riquelme (Demo HU-13)',
                    'password' => Hash::make(self::PASSWORD),
                    'rol' => null,
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                ],
            );
            $estudiante = Estudiante::query()->updateOrCreate(
                ['user_id' => $usuario->id],
                ['anio_ingreso' => (int) $hoy->year - 2],
            );
            $habilitacion = Habilitacion::query()->updateOrCreate(
                [
                    'estudiante_id' => $estudiante->id,
                    'tipo_practica_id' => $tipo->id,
                    'periodo_id' => $periodo->id,
                ],
                ['cumple_prerrequisito' => true, 'horas_requeridas' => $tipo->horas_requeridas],
            );

            $centro = CentroPractica::query()->updateOrCreate(
                ['rut' => '76.223.456-0'],
                [
                    'razon_social' => 'Estudio Horizonte Digital SpA (Demo)',
                    'nombre' => 'Estudio Horizonte Digital',
                    'direccion' => 'Av. 18 de Septiembre 1250, Arica (dato ficticio)',
                    'telefono' => '+56 58 220 0000',
                    'es_interno' => false,
                    'fecha_alta' => $hoy->subYears(2)->toDateString(),
                    'fecha_baja' => null,
                    'motivo_baja' => null,
                ],
            );
            AcuerdoColaboracion::query()->updateOrCreate(
                ['centro_id' => $centro->id, 'tipo' => 'tradicional'],
                [
                    'representante_legal' => 'Representante Demo',
                    'fecha_inicio' => $hoy->subYear()->toDateString(),
                    'fecha_termino' => $hoy->addYear()->toDateString(),
                    'archivo_pdf' => null,
                ],
            );
            $supervisor = Supervisor::query()->updateOrCreate(
                ['centro_id' => $centro->id, 'correo' => 'supervisor.hu13@example.test'],
                [
                    'nombre' => 'Paula Contreras (Demo)',
                    'telefono' => '+56 9 5555 0130',
                    'cargo' => 'Diseñadora multimedia',
                    'unidad' => 'Diseño digital',
                    'fecha_alta' => $hoy->subYears(2)->toDateString(),
                    'fecha_baja' => null,
                ],
            );

            $practica = Practica::query()->updateOrCreate(
                ['habilitacion_id' => $habilitacion->id],
                [
                    'supervisor_id' => $supervisor->id,
                    'origen' => 'asignacion',
                    'modalidad' => 'presencial',
                    'tareas' => 'Diseño de piezas digitales y apoyo en contenidos para redes sociales.',
                    'fecha_inicio' => $inicio->toDateString(),
                    'fecha_fin' => $termino->toDateString(),
                ],
            );

            $practica->historiales()->delete();
            HistorialPractica::query()->create([
                'practica_id' => $practica->id,
                'user_id' => $usuario->id,
                'fecha_hora' => $inicio->addDays(2)->setTime(9, 0),
                'estado_nuevo' => 'confirmada',
            ]);
            $coordinadora = User::query()->where('rol', 'coordinadora')->orderBy('id')->first();
            HistorialPractica::query()->create([
                'practica_id' => $practica->id,
                'user_id' => $coordinadora?->id ?? $usuario->id,
                'fecha_hora' => $hoy->subWeeks(4)->setTime(9, 0),
                'estado_nuevo' => 'en_curso',
            ]);

            DB::table('horarios')->where('practica_id', $practica->id)->delete();
            foreach ([1, 2, 3, 4, 5] as $dia) {
                $practica->horarios()->create([
                    'dia_semana' => $dia,
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '13:00:00',
                    'vigente_desde' => $inicio->toDateString(),
                ]);
            }

            DB::table('registros_diarios')->where('practica_id', $practica->id)->delete();
            $diasHabiles = [];
            for ($fecha = $inicio; $fecha->lessThanOrEqualTo($hoy); $fecha = $fecha->addDay()) {
                if ($fecha->dayOfWeekIso <= 5) $diasHabiles[] = $fecha;
            }
            if (count($diasHabiles) < 12) {
                throw new RuntimeException('No hay suficientes días hábiles para preparar los registros demo.');
            }

            // Seis jornadas confirmadas: 5 x 4 h + 1 x 2 h = 22 de 64 horas.
            foreach (array_slice($diasHabiles, 1, 6) as $indice => $fecha) {
                $dosHoras = $indice === 5;
                $this->registro($practica, $fecha, [
                    'hora_entrada' => '09:00:00',
                    'hora_salida' => $dosHoras ? '11:00:00' : '13:00:00',
                    'actividades' => $dosHoras
                        ? 'Reunión de coordinación y planificación de piezas para redes.'
                        : 'Diseño y revisión de piezas digitales junto al equipo del centro.',
                    'validacion' => 'confirmado',
                ]);
            }

            $inasistencia = $diasHabiles[count($diasHabiles) - 6];
            $recuperacion = $diasHabiles[count($diasHabiles) - 5];
            $this->registro($practica, $inasistencia, [
                'actividades' => null,
                'validacion' => 'confirmado',
                'justificacion' => 'Inasistencia informada y aceptada para demostración.',
                'fecha_justificacion' => $inasistencia->setTime(10, 0),
                'recuperacion_programada' => $recuperacion->toDateString(),
            ]);
            $this->registro($practica, $recuperacion, [
                'hora_entrada' => '09:00:00',
                'hora_salida' => '13:00:00',
                'actividades' => 'Recuperación de jornada acordada con el centro.',
                'validacion' => null,
            ]);

            $rechazada = $diasHabiles[count($diasHabiles) - 3];
            $this->registro($practica, $rechazada, [
                'hora_entrada' => '09:00:00',
                'hora_salida' => '13:00:00',
                'actividades' => 'Apoyo en diseño de material para difusión.',
                'validacion' => 'rechazado',
            ]);
            $pendiente = $diasHabiles[count($diasHabiles) - 1];
            $this->registro($practica, $pendiente, [
                'hora_entrada' => '09:00:00',
                'hora_salida' => '13:00:00',
                'actividades' => 'Preparación de recursos gráficos para una publicación digital.',
                'validacion' => null,
            ]);

            $this->command?->info('Demo HU-13 lista. Cuenta: '.self::EMAIL.' / '.self::PASSWORD);
            $this->command?->line('Práctica en curso: '.$tipo->nombre.' en '.$centro->nombre.'. Horas confirmadas: 22 de '.$tipo->horas_requeridas.'.');
            $this->command?->warn('Los datos y correos de supervisor son ficticios; el dominio example.test no recibe correo real.');
        });
    }

    private function registro(Practica $practica, CarbonImmutable $fecha, array $datos): void
    {
        RegistroDiario::query()->create([
            'practica_id' => $practica->id,
            'fecha' => $fecha->toDateString(),
            'hora_entrada' => null,
            'hora_salida' => null,
            'actividades' => null,
            'validacion' => null,
            'justificacion' => null,
            'fecha_justificacion' => null,
            'recuperacion_programada' => null,
            ...$datos,
        ]);
    }
}
