<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Datos base que el sistema necesita para funcionar: período actual, mallas con sus prácticas y áreas.
class CatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $ahora = now();

        DB::table('periodos')->insert([
            ['anio' => 2026, 'semestre' => 1, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['anio' => 2026, 'semestre' => 2, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        // Malla actual: 3 prácticas (HU-06)
        $actual = DB::table('mallas')->insertGetId([
            'nombre' => 'Malla actual', 'vigente_desde' => '2018-03-01', 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        DB::table('tipos_practica')->insert([
            ['malla_id' => $actual, 'nombre' => 'Laboral I', 'categoria' => 'laboral', 'horas_requeridas' => 64, 'prerrequisito' => 'Primer año aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['malla_id' => $actual, 'nombre' => 'Laboral II', 'categoria' => 'laboral', 'horas_requeridas' => 64, 'prerrequisito' => 'Laboral I aprobada', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['malla_id' => $actual, 'nombre' => 'Profesional', 'categoria' => 'profesional', 'horas_requeridas' => 320, 'prerrequisito' => 'Laboral II aprobada', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        // Malla nueva en preparación: 2 prácticas
        $nueva = DB::table('mallas')->insertGetId([
            'nombre' => 'Malla nueva', 'vigente_desde' => null, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);
        DB::table('tipos_practica')->insert([
            ['malla_id' => $nueva, 'nombre' => 'Laboral', 'categoria' => 'laboral', 'horas_requeridas' => 96, 'prerrequisito' => 'Primer año aprobado', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['malla_id' => $nueva, 'nombre' => 'Profesional', 'categoria' => 'profesional', 'horas_requeridas' => 240, 'prerrequisito' => 'Laboral aprobada', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        foreach (['Diseño gráfico', 'Web', 'Animación', 'Audiovisual', 'Editorial', 'Redes sociales'] as $area) {
            DB::table('areas')->insert(['nombre' => $area, 'created_at' => $ahora, 'updated_at' => $ahora]);
        }
    }
}
