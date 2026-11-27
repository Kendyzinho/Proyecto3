<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-14: la alerta se resuelve sola cuando desaparece su causa, y la Coordinadora anota qué hizo al atenderla
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dateTime('fecha_resolucion')->nullable()->after('fecha_atencion');
            $table->string('nota_atencion', 500)->nullable()->after('fecha_resolucion');
        });
    }

    public function down(): void
    {
        Schema::table('alertas', function (Blueprint $table) {
            $table->dropColumn(['fecha_resolucion', 'nota_atencion']);
        });
    }
};
