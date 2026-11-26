<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-14: el día (o el lunes de la semana) que originó cada alerta, para no repetirla
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->date('fecha_referencia')->nullable()->after('tipo');
            $table->unique(['practica_id', 'tipo', 'fecha_referencia']);
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropUnique(['practica_id', 'tipo', 'fecha_referencia']);
            $table->dropColumn('fecha_referencia');
        });
    }
};
