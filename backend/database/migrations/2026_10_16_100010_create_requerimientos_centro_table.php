<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// REQUERIMIENTO_CENTRO y REQUERIMIENTO_ÁREA: lo que pide cada centro por categoría de práctica
// (formularios "Solicitud de estudiante para Práctica Laboral/Profesional"). Se usa en HU-08 y HU-12.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requerimientos_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros_practica')->cascadeOnDelete();
            $table->foreignId('periodo_id')->constrained('periodos'); // la convocatoria es cada semestre
            $table->enum('categoria', ['laboral', 'profesional']);
            $table->tinyInteger('cupos')->unsigned(); // 1 o 2
            $table->timestamps();
            $table->unique(['centro_id', 'periodo_id', 'categoria']);
        });

        Schema::create('requerimiento_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requerimiento_id')->constrained('requerimientos_centro')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas');
            $table->timestamps();
            $table->unique(['requerimiento_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requerimiento_area');
        Schema::dropIfExists('requerimientos_centro');
    }
};
