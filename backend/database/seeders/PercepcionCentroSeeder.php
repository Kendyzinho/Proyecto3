<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PercepcionCentroSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $plantilla = DB::table('plantillas_instrumento')->where('instrumento', 'encuesta')
                ->whereNull('tipo_practica_id')->where('vigente_desde', '2026-01-01')->first();
            $plantillaId = $plantilla?->id ?? DB::table('plantillas_instrumento')->insertGetId([
                'tipo_practica_id' => null, 'instrumento' => 'encuesta', 'vigente_desde' => '2026-01-01',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([
                ['clima_laboral', 'Clima laboral', 'escala'],
                ['pago_viatico', 'Pago o viático', 'escala'],
                ['pertinencia_tareas', 'Pertinencia de las tareas', 'escala'],
                ['observaciones', 'Observaciones', 'texto'],
            ] as $orden => [$clave, $enunciado, $tipo]) {
                $item = DB::table('items')->where('plantilla_id', $plantillaId)->where('clave', $clave)->first();
                $itemId = $item?->id ?? DB::table('items')->insertGetId([
                    'plantilla_id' => $plantillaId, 'clave' => $clave, 'enunciado' => $enunciado,
                    'tipo_respuesta' => $tipo, 'obligatorio' => $tipo === 'escala', 'orden' => $orden + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($tipo === 'escala') {
                    foreach (range(1, 5) as $valor) {
                        DB::table('opciones')->updateOrInsert(['item_id' => $itemId, 'orden' => $valor], [
                            'etiqueta' => (string) $valor, 'valor_numerico' => $valor, 'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }
}
