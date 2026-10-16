<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CONFIRMACIÓN_SEMANAL: firma semanal del supervisor (HU-26).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmaciones_semanales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->date('semana'); // fecha del lunes
            $table->foreignId('supervisor_id')->constrained('supervisores');
            $table->dateTime('fecha_hora_envio');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['practica_id', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmaciones_semanales');
    }
};
