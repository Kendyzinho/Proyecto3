<?php

use App\Support\Rut;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// HU-09 y HU-12 guardaban el RUT sin puntos, y el resto del sistema con puntos.
// Si la versión con puntos ya existe, se deja como está para no chocar con el índice único.
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('centros_practica')->whereNotNull('rut')->get(['id', 'rut']) as $centro) {
            $rut = Rut::normalizar($centro->rut);
            if ($rut !== null && $rut !== $centro->rut && ! DB::table('centros_practica')->where('rut', $rut)->exists()) {
                DB::table('centros_practica')->where('id', $centro->id)->update(['rut' => $rut]);
            }
        }
    }

    public function down(): void
    {
        // El formato anterior no aporta nada: no se revierte
    }
};
