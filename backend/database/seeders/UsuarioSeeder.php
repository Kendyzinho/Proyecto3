<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Usuarios de prueba del README. Contraseña de todos: password
class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Secretaria de prueba', 'email' => 'secretaria@uta.cl', 'rol' => 'secretaria']);
        User::factory()->create(['name' => 'Coordinadora de prueba', 'email' => 'coordinadora@uta.cl', 'rol' => 'coordinadora']);
        User::factory()->create(['name' => 'Jefe de Carrera de prueba', 'email' => 'jefe@uta.cl', 'rol' => 'jefe']);

        $estudiante = User::factory()->create([
            'rut' => '20.456.789-1',
            'name' => 'Matías Herrera Lillo',
            'email' => 'estudiante@alumnos.uta.cl',
            'rol' => null,
        ]);

        $ahora = now();
        $estudianteId = DB::table('estudiantes')->insertGetId([
            'user_id' => $estudiante->id, 'anio_ingreso' => 2022, 'created_at' => $ahora, 'updated_at' => $ahora,
        ]);

        // Habilitado para la Práctica Profesional del 2º semestre 2026
        $profesional = DB::table('tipos_practica')->where('nombre', 'Profesional')->where('horas_requeridas', 320)->first();
        $periodo = DB::table('periodos')->where('anio', 2026)->where('semestre', 2)->first();
        DB::table('habilitaciones')->insert([
            'estudiante_id' => $estudianteId,
            'tipo_practica_id' => $profesional->id,
            'periodo_id' => $periodo->id,
            'cumple_prerrequisito' => true,
            'horas_requeridas' => $profesional->horas_requeridas,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }
}
