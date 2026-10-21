<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Datos aislados e idempotentes para probar HU-08 en practicas_test.
 * Ejecutar explícitamente; no se agrega al seeder general del proyecto.
 */
class Hu08PruebaSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('requerimientos_centro')) {
            $this->command?->error('Falta la tabla requerimientos_centro. Ejecuta php artisan migrate primero.');
            return;
        }

        $ahora = now();
        $periodo = DB::table('periodos')->where('anio', 2026)->where('semestre', 2)->first();
        $tipo = DB::table('tipos_practica')->where('nombre', 'Laboral I')->whereNull('fecha_baja')->first();
        $areas = DB::table('areas')->whereIn('nombre', ['Web', 'Animación'])->pluck('id', 'nombre');

        if (!$periodo || !$tipo || $areas->count() < 2) {
            $this->command?->error('Primero ejecuta las migraciones y los seeders CatalogoSeeder y UsuarioSeeder.');
            return;
        }

        foreach ([
            ['nombre' => 'Estudiante HU08 Web', 'correo' => 'hu08.web@alumnos.uta.cl', 'rut' => '20.000.008-2', 'area' => 'Web'],
            ['nombre' => 'Estudiante HU08 Animación', 'correo' => 'hu08.animacion@alumnos.uta.cl', 'rut' => '20.000.009-0', 'area' => 'Animación'],
        ] as $datos) {
            $user = User::firstOrCreate(
                ['email' => $datos['correo']],
                ['name' => $datos['nombre'], 'rut' => $datos['rut'], 'password' => Hash::make('password'), 'rol' => null],
            );
            $estudiante = DB::table('estudiantes')->where('user_id', $user->id)->first();
            if (!$estudiante) {
                $estudianteId = DB::table('estudiantes')->insertGetId([
                    'user_id' => $user->id, 'anio_ingreso' => 2024, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            } else {
                $estudianteId = $estudiante->id;
            }

            DB::table('estudiante_area')->insertOrIgnore([
                'estudiante_id' => $estudianteId,
                'area_id' => $areas[$datos['area']],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $habilitacion = DB::table('habilitaciones')->where('estudiante_id', $estudianteId)
                ->where('tipo_practica_id', $tipo->id)->where('periodo_id', $periodo->id)->first();
            if (!$habilitacion) {
                DB::table('habilitaciones')->insert([
                    'estudiante_id' => $estudianteId,
                    'tipo_practica_id' => $tipo->id,
                    'periodo_id' => $periodo->id,
                    'cumple_prerrequisito' => true,
                    'horas_requeridas' => $tipo->horas_requeridas,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        $centro = DB::table('centros_practica')->where('rut', '76.008.008-5')->first();
        if (!$centro) {
            $centroId = DB::table('centros_practica')->insertGetId([
                'rut' => '76.008.008-5',
                'razon_social' => 'Centro de Prueba HU-08 SpA',
                'nombre' => 'Centro de Prueba HU-08',
                'direccion' => 'Dirección ficticia para pruebas',
                'es_interno' => false,
                'fecha_alta' => '2026-01-01',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        } else {
            $centroId = $centro->id;
        }

        foreach ($areas as $areaId) {
            DB::table('centro_area')->insertOrIgnore([
                'centro_id' => $centroId, 'area_id' => $areaId,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        foreach ([
            ['nombre' => 'Supervisora HU08 Web', 'correo' => 'supervisora.web@prueba.cl'],
            ['nombre' => 'Supervisor HU08 Animación', 'correo' => 'supervisor.animacion@prueba.cl'],
        ] as $datos) {
            $existe = DB::table('supervisores')->where('centro_id', $centroId)->where('correo', $datos['correo'])->exists();
            if (!$existe) DB::table('supervisores')->insert([
                'centro_id' => $centroId, 'nombre' => $datos['nombre'], 'correo' => $datos['correo'],
                'fecha_alta' => '2026-01-01', 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        if (!DB::table('acuerdos_colaboracion')->where('centro_id', $centroId)->exists()) {
            DB::table('acuerdos_colaboracion')->insert([
                'centro_id' => $centroId,
                'tipo' => 'tradicional',
                'representante_legal' => 'Representante ficticio HU-08',
                'fecha_inicio' => '2026-01-01',
                'fecha_termino' => '2026-12-31',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $requerimiento = DB::table('requerimientos_centro')->where('centro_id', $centroId)
            ->where('periodo_id', $periodo->id)->where('categoria', 'laboral')->first();
        if (!$requerimiento) {
            DB::table('requerimientos_centro')->insert([
                'centro_id' => $centroId, 'periodo_id' => $periodo->id, 'categoria' => 'laboral',
                'cupos' => 2, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        $this->command?->info('Datos HU-08 listos: 2 estudiantes habilitados, un centro laboral activo, 2 supervisores y 2 cupos para 2º semestre 2026.');
        $this->command?->line('Estudiantes de prueba: hu08.web@alumnos.uta.cl y hu08.animacion@alumnos.uta.cl (contraseña: password).');
    }
}
