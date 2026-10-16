<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// TIPO_PRÁCTICA: prácticas de cada malla con sus horas (HU-06).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_practica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('malla_id')->constrained('mallas');
            $table->string('nombre'); // Laboral I, Laboral II, Profesional...
            $table->enum('categoria', ['laboral', 'profesional']);
            $table->smallInteger('horas_requeridas')->unsigned();
            $table->string('prerrequisito')->nullable();
            $table->date('fecha_baja')->nullable(); // vacía = activo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_practica');
    }
};
