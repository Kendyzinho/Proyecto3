<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// REGISTRO_DIARIO: bitácora diaria del estudiante (HU-13).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->date('fecha');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->text('actividades')->nullable(); // relato del día
            $table->enum('validacion', ['confirmado', 'rechazado'])->nullable(); // vacía = sin confirmar
            $table->text('justificacion')->nullable();
            $table->dateTime('fecha_justificacion')->nullable(); // plazo de 24 h (Art. 18)
            $table->date('recuperacion_programada')->nullable(); // Art. 19
            $table->timestamps();
            $table->unique(['practica_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_diarios');
    }
};
