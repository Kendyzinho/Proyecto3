<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RESPUESTA_INSTRUMENTO: un formulario respondido (HU-16, HU-19, HU-20).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respuestas_instrumento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_instrumento');
            $table->foreignId('practica_id')->constrained('practicas');
            $table->foreignId('visita_id')->nullable()->constrained('visitas_tecnicas'); // solo en la pauta de visita
            $table->dateTime('fecha_envio')->nullable(); // vacía = borrador
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respuestas_instrumento');
    }
};
