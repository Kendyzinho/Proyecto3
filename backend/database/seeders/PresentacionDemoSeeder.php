<?php

namespace Database\Seeders;

use App\Models\CentroPractica;
use App\Models\Area;
use App\Models\Estudiante;
use App\Models\HistorialPractica;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Datos ficticios y repetibles para demostrar HU-08, HU-09 y HU-12 en la base
 * de desarrollo `practicas`. No se agrega a DatabaseSeeder ni se usa en tests.
 * Ejecutar explícitamente con: php artisan db:seed --class=PresentacionDemoSeeder
 */
class PresentacionDemoSeeder extends Seeder
{
    private const PASSWORD = 'DemoHU2026!';

    public function run(): void
    {
        foreach ([
            'users', 'estudiantes', 'periodos', 'tipos_practica', 'mallas', 'areas',
            'habilitaciones', 'estudiante_area', 'centros_practica', 'centro_area',
            'supervisores', 'acuerdos_colaboracion', 'requerimientos_centro',
            'requerimiento_area', 'practicas', 'historiales_practica', 'horarios',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Falta la tabla {$table}. Ejecuta las migraciones antes de este seeder.");
            }
        }

        DB::transaction(function (): void {
            $periodoId = $this->periodoActualDemo();
            $tipoLaboral = $this->tipoVigente('laboral');
            $tipoProfesional = $this->tipoVigente('profesional');
            $areas = $this->areasDemo();

            $coordinadora = $this->usuario('demo.coordinadora@uta.cl', 'Coordinadora Demo', 'coordinadora');
            $estudiantes = [
                'hu08_web' => $this->estudiante('demo.hu08.web@alumnos.uta.cl', 'Valentina Soto Riquelme', 2024),
                'hu08_animacion' => $this->estudiante('demo.hu08.animacion@alumnos.uta.cl', 'Tomás Araya Molina', 2023),
                'hu09_por_revisar' => $this->estudiante('demo.hu09.porrevisar@alumnos.uta.cl', 'Camila Rojas Pizarro', 2022),
                'hu09_devuelta' => $this->estudiante('demo.hu09.devuelta@alumnos.uta.cl', 'Diego Fuentes Leiva', 2022),
                'hu09_confirmada' => $this->estudiante('demo.hu09.confirmada@alumnos.uta.cl', 'Josefa Núñez Castillo', 2022),
                'hu09_centro_propuesto' => $this->estudiante('demo.hu09.propuesto@alumnos.uta.cl', 'Martín López Olivares', 2022),
                'hu09_nueva' => $this->estudiante('demo.hu09.nueva@alumnos.uta.cl', 'Fernanda Contreras Rojas', 2022),
            ];

            $uta = $this->centroInterno();
            $supervisoresUta = [
                $this->supervisor($uta, 'Andrea Valdés Demo', 'andrea.valdes@example.test', 'Diseñadora multimedia', 'Escuela de Diseño'),
                $this->supervisor($uta, 'Felipe Cárdenas Demo', 'felipe.cardenas@example.test', 'Desarrollador web', 'Unidad Digital'),
                $this->supervisor($uta, 'María Paz Silva Demo', 'maria.silva@example.test', 'Productora audiovisual', 'Comunicaciones'),
            ];

            $this->vincularAreas($uta, [$areas['web'], $areas['animacion'], $areas['audiovisual'], $areas['diseno']]);
            $this->requerimiento($uta, $periodoId, 'laboral', 4, [$areas['web'], $areas['animacion'], $areas['diseno']]);
            $this->requerimiento($uta, $periodoId, 'profesional', 4, [$areas['web'], $areas['animacion'], $areas['audiovisual'], $areas['diseno']]);

            // HU-12: una organización histórica con baja y motivo visible en su ficha.
            $centroBaja = $this->centroExterno(
                '76111222', 'Editorial Costera Demo SpA', 'Editorial Costera',
                'Av. Costanera 1840, Arica', today()->subYears(2), today()->subMonths(2),
                'Centro ficticio dado de baja para demostrar el motivo de baja.',
            );
            $supervisorBaja = $this->supervisor($centroBaja, 'Elena Paredes Demo', 'elena.paredes@example.test', 'Editora', 'Editorial');
            $supervisorBaja->update(['fecha_alta' => today()->subYears(2)->toDateString(), 'fecha_baja' => null]);
            $this->vincularAreas($centroBaja, [$areas['diseno']]);

            // HU-09: propuesta pendiente; no se le asignan cupos ni acuerdo mientras siga propuesta.
            $centroPropuesto = CentroPractica::query()->firstOrCreate(
                ['rut' => $this->rut('76222333')],
                $this->centroData([
                    'razon_social' => 'Estudio Bruma Editorial SpA',
                    'nombre' => 'Estudio Bruma Editorial',
                    'direccion' => 'Sector centro, Arica (dato ficticio)',
                    'es_interno' => false,
                    'fecha_alta' => null,
                    'fecha_baja' => null,
                ]),
            );
            $supervisorPropuesto = $centroPropuesto->supervisores()->firstOrCreate(
                ['correo' => 'lucas.morales@example.test'],
                [
                    'nombre' => 'Lucas Morales Demo',
                    'telefono' => '+56 9 5555 0102',
                    'cargo' => 'Director de arte',
                    'unidad' => 'Diseño editorial',
                    'fecha_alta' => null,
                    'fecha_baja' => null,
                ],
            );

            $this->habilitacion($estudiantes['hu08_web'], $tipoLaboral, $periodoId);
            $this->habilitacion($estudiantes['hu08_animacion'], $tipoLaboral, $periodoId);
            foreach (['hu09_por_revisar', 'hu09_devuelta', 'hu09_confirmada', 'hu09_centro_propuesto', 'hu09_nueva'] as $key) {
                $this->habilitacion($estudiantes[$key], $tipoProfesional, $periodoId);
            }

            $this->asignarAreaEstudiante($estudiantes['hu08_web'], $areas['web']);
            $this->asignarAreaEstudiante($estudiantes['hu08_animacion'], $areas['animacion']);
            $this->asignarAreaEstudiante($estudiantes['hu09_por_revisar'], $areas['diseno']);
            $this->asignarAreaEstudiante($estudiantes['hu09_devuelta'], $areas['audiovisual']);
            $this->asignarAreaEstudiante($estudiantes['hu09_confirmada'], $areas['web']);
            $this->asignarAreaEstudiante($estudiantes['hu09_centro_propuesto'], $areas['diseno']);
            $this->asignarAreaEstudiante($estudiantes['hu09_nueva'], $areas['diseno']);

            $this->practicaDemo(
                $estudiantes['hu09_por_revisar'], $tipoProfesional, $periodoId, $supervisoresUta[0],
                $coordinadora, $estudiantes['hu09_por_revisar'], 'por_revisar', null, false, false,
                'Diseño de piezas para campaña digital y apoyo en la actualización del sitio institucional.',
            );
            $this->practicaDemo(
                $estudiantes['hu09_devuelta'], $tipoProfesional, $periodoId, $supervisoresUta[1],
                $coordinadora, $estudiantes['hu09_devuelta'], 'devuelta',
                'Precisar las tareas de desarrollo y adjuntar un horario compatible con la jornada del centro.', false, false,
                'Apoyo en diseño de interfaz y actualización de contenidos web.',
            );
            $this->practicaDemo(
                $estudiantes['hu09_confirmada'], $tipoProfesional, $periodoId, $supervisoresUta[2],
                $coordinadora, $estudiantes['hu09_confirmada'], 'confirmada', null, false, false,
                'Producción de recursos audiovisuales y material gráfico para difusión institucional.',
            );
            $this->practicaDemo(
                $estudiantes['hu09_centro_propuesto'], $tipoProfesional, $periodoId, $supervisorPropuesto,
                $coordinadora, $estudiantes['hu09_centro_propuesto'], 'por_revisar', null, true, true,
                'Diseño editorial y adaptación de piezas gráficas para publicaciones digitales.',
            );

            $this->command?->info('Datos de demostración HU-08, HU-09 y HU-12 listos para el período 2º semestre 2026.');
            $this->command?->line('Inicio de sesión Coordinación: demo.coordinadora@uta.cl / '.self::PASSWORD);
            $this->command?->line('Estudiantes de muestra: demo.hu08.web@alumnos.uta.cl, demo.hu08.animacion@alumnos.uta.cl, demo.hu09.porrevisar@alumnos.uta.cl, demo.hu09.devuelta@alumnos.uta.cl, demo.hu09.confirmada@alumnos.uta.cl, demo.hu09.propuesto@alumnos.uta.cl y demo.hu09.nueva@alumnos.uta.cl / '.self::PASSWORD);
            $this->command?->warn('Datos ficticios: los correos usan example.test y no deben enviarse a destinatarios reales.');
        });
    }

    private function periodoActualDemo(): int
    {
        return (int) Periodo::query()->firstOrCreate(['anio' => 2026, 'semestre' => 2])->id;
    }

    private function tipoVigente(string $categoria): object
    {
        $tipo = DB::table('tipos_practica as tp')
            ->join('mallas as m', 'm.id', '=', 'tp.malla_id')
            ->where('tp.categoria', $categoria)
            ->whereNull('tp.fecha_baja')
            ->whereNotNull('m.vigente_desde')
            ->orderBy('tp.id')
            ->select('tp.id', 'tp.horas_requeridas')
            ->first();

        if (! $tipo) {
            throw new RuntimeException("No hay un tipo de práctica vigente de categoría {$categoria}.");
        }

        return $tipo;
    }

    private function areasDemo(): array
    {
        $nombres = [
            'web' => 'Diseño y desarrollo web',
            'animacion' => 'Animación digital',
            'audiovisual' => 'Producción audiovisual',
            'diseno' => 'Diseño gráfico y diagramación',
        ];

        foreach ($nombres as $key => $nombre) {
            $nombres[$key] = (int) Area::query()->firstOrCreate(['nombre' => $nombre])->id;
        }

        return $nombres;
    }

    private function usuario(string $email, string $nombre, string $rol): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => Hash::make(self::PASSWORD), 'rol' => $rol, 'fecha_baja' => null, 'motivo_baja' => null],
        );
    }

    private function estudiante(string $email, string $nombre, int $anioIngreso): object
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $nombre, 'password' => Hash::make(self::PASSWORD), 'rol' => null, 'fecha_baja' => null, 'motivo_baja' => null],
        );
        $estudiante = Estudiante::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['anio_ingreso' => $anioIngreso],
        );

        return (object) ['user' => $user, 'ficha' => $estudiante];
    }

    private function centroInterno(): CentroPractica
    {
        $centro = CentroPractica::query()->where('es_interno', true)->first();
        if (! $centro) {
            $centro = CentroPractica::query()->create($this->centroData([
                'rut' => $this->rut('70770800'),
                'razon_social' => 'Universidad de Tarapacá',
                'nombre' => 'Universidad de Tarapacá',
                'direccion' => 'Av. 18 de Septiembre 2222, Arica',
                'es_interno' => true,
                'fecha_alta' => today()->toDateString(),
            ]));
        }

        $centro->update(['fecha_alta' => $centro->fecha_alta ?? today()->toDateString(), 'fecha_baja' => null, 'motivo_baja' => null]);

        return $centro->fresh();
    }

    private function centroExterno(string $rutBase, string $razonSocial, string $nombre, string $direccion, mixed $fechaAlta, mixed $fechaBaja, string $motivoBaja): CentroPractica
    {
        return CentroPractica::query()->updateOrCreate(
            ['rut' => $this->rut($rutBase)],
            $this->centroData([
                'razon_social' => $razonSocial,
                'nombre' => $nombre,
                'direccion' => $direccion,
                'es_interno' => false,
                'fecha_alta' => $fechaAlta?->toDateString(),
                'fecha_baja' => $fechaBaja?->toDateString(),
                'motivo_baja' => $motivoBaja,
            ]),
        );
    }

    /** Adapta la carga al esquema instalado; practicas conserva una columna cupos antigua. */
    private function centroData(array $attributes): array
    {
        if (Schema::hasColumn('centros_practica', 'cupos')) {
            $attributes['cupos'] = 0; // los cupos por período/categoría viven en requerimientos_centro
        }

        return array_filter($attributes, fn ($value, $column) => Schema::hasColumn('centros_practica', $column), ARRAY_FILTER_USE_BOTH);
    }

    private function supervisor(CentroPractica $centro, string $nombre, string $correo, string $cargo, string $unidad): Supervisor
    {
        return $centro->supervisores()->updateOrCreate(
            ['correo' => $correo],
            [
                'nombre' => $nombre,
                'telefono' => '+56 9 5555 0100',
                'cargo' => $cargo,
                'unidad' => $unidad,
                'fecha_alta' => today()->toDateString(),
                'fecha_baja' => null,
            ],
        );
    }

    private function vincularAreas(CentroPractica $centro, array $areaIds): void
    {
        foreach ($areaIds as $areaId) {
            DB::table('centro_area')->insertOrIgnore([
                'centro_id' => $centro->id,
                'area_id' => $areaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function requerimiento(CentroPractica $centro, int $periodoId, string $categoria, int $cupos, array $areaIds): void
    {
        $requerimiento = DB::table('requerimientos_centro')->updateOrInsert(
            ['centro_id' => $centro->id, 'periodo_id' => $periodoId, 'categoria' => $categoria],
            ['cupos' => $cupos, 'updated_at' => now(), 'created_at' => now()],
        );
        $requerimientoId = DB::table('requerimientos_centro')
            ->where('centro_id', $centro->id)->where('periodo_id', $periodoId)->where('categoria', $categoria)->value('id');

        foreach ($areaIds as $areaId) {
            DB::table('requerimiento_area')->insertOrIgnore([
                'requerimiento_id' => $requerimientoId,
                'area_id' => $areaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function habilitacion(object $estudiante, object $tipo, int $periodoId): int
    {
        $habilitacion = DB::table('habilitaciones')->updateOrInsert(
            ['estudiante_id' => $estudiante->ficha->id, 'tipo_practica_id' => $tipo->id, 'periodo_id' => $periodoId],
            ['cumple_prerrequisito' => true, 'horas_requeridas' => $tipo->horas_requeridas, 'updated_at' => now(), 'created_at' => now()],
        );

        return (int) DB::table('habilitaciones')
            ->where('estudiante_id', $estudiante->ficha->id)->where('tipo_practica_id', $tipo->id)->where('periodo_id', $periodoId)->value('id');
    }

    private function asignarAreaEstudiante(object $estudiante, int $areaId): void
    {
        DB::table('estudiante_area')->insertOrIgnore([
            'estudiante_id' => $estudiante->ficha->id,
            'area_id' => $areaId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function practicaDemo(object $estudiante, object $tipo, int $periodoId, Supervisor $supervisor, User $coordinadora, object $actor, string $estado, ?string $motivo, bool $centroPropuesto, bool $supervisorPropuesto, string $tareas): void
    {
        $habilitacionId = (int) DB::table('habilitaciones')
            ->where('estudiante_id', $estudiante->ficha->id)->where('tipo_practica_id', $tipo->id)->where('periodo_id', $periodoId)->value('id');
        $practica = Practica::query()->updateOrCreate(
            ['habilitacion_id' => $habilitacionId, 'origen' => 'solicitud'],
            [
                'supervisor_id' => $supervisor->id,
                'centro_propuesto_en_solicitud' => $centroPropuesto,
                'supervisor_propuesto_en_solicitud' => $supervisorPropuesto,
                'modalidad' => 'presencial',
                'tareas' => $tareas,
                'fecha_inicio' => today()->addDays(5)->toDateString(),
                'fecha_fin' => today()->addDays(50)->toDateString(),
            ],
        );

        DB::table('horarios')->where('practica_id', $practica->id)->delete();
        foreach ([1, 2, 3, 4, 5] as $dia) {
            DB::table('horarios')->insert([
                'practica_id' => $practica->id,
                'dia_semana' => $dia,
                'hora_inicio' => '09:00:00',
                'hora_fin' => '13:00:00',
                'vigente_desde' => today()->addDays(5)->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('historiales_practica')->where('practica_id', $practica->id)->delete();
        HistorialPractica::query()->create([
            'practica_id' => $practica->id,
            'user_id' => $actor->user->id,
            'fecha_hora' => now()->subDays(4),
            'estado_nuevo' => 'por_revisar',
            'motivo' => null,
        ]);
        if ($estado !== 'por_revisar') {
            HistorialPractica::query()->create([
                'practica_id' => $practica->id,
                'user_id' => $coordinadora->id,
                'fecha_hora' => now()->subDays(2),
                'estado_nuevo' => $estado,
                'motivo' => $motivo,
            ]);
        }
    }

    private function rut(string $cuerpo): string
    {
        $cuerpo = preg_replace('/\D/', '', $cuerpo) ?? '';

        return number_format((int) $cuerpo, 0, '', '.').'-'.Rut::digitoVerificador($cuerpo);
    }
}
