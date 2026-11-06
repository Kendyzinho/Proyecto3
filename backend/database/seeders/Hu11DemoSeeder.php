<?php

namespace Database\Seeders;

use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\TipoPractica;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

// Optativo, no incluido en DatabaseSeeder. Ejecutar solo con aprobación para desarrollo.
class Hu11DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || app()->configurationIsCached()
            || DB::connection()->getDatabaseName() !== 'proyecto3_integrado'
            || DB::selectOne('SELECT DATABASE() AS nombre')->nombre !== 'proyecto3_integrado') {
            throw new LogicException('Este seeder solo admite desarrollo local en proyecto3_integrado.');
        }
        $periodo = Periodo::actual();
        $tipo = TipoPractica::whereHas('malla', fn ($q) => $q->whereNotNull('vigente_desde'))
            ->where('nombre', 'Laboral I')->whereNull('fecha_baja')->firstOrFail();
        if (! $periodo) {
            throw new LogicException('Primero debe existir un período académico.');
        }

        DB::transaction(function () use ($periodo, $tipo) {
            $existente = User::where('rut', '22.000.001-K')->orWhere('email', 'hu11.demo@alumnos.uta.cl')->first();
            if ($existente && ($existente->rut !== '22.000.001-K' || $existente->email !== 'hu11.demo@alumnos.uta.cl' || $existente->rol !== null)) {
                throw new LogicException('Los identificadores de demostración pertenecen a otra cuenta.');
            }
            $usuario = $existente ?? User::create(['rut' => '22.000.001-K', 'name' => 'Estudiante de demostración HU-11',
                'email' => 'hu11.demo@alumnos.uta.cl', 'rol' => null, 'password' => Str::random(40)]);
            $estudiante = Estudiante::firstOrCreate(['user_id' => $usuario->id], ['anio_ingreso' => 2024]);
            Habilitacion::firstOrCreate(['estudiante_id' => $estudiante->id, 'tipo_practica_id' => $tipo->id, 'periodo_id' => $periodo->id],
                ['cumple_prerrequisito' => true, 'horas_requeridas' => $tipo->horas_requeridas]);
        });
    }
}
