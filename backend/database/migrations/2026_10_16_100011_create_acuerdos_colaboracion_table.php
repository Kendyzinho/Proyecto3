<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ACUERDO_COLABORACIÓN: sin acuerdo vigente el centro no recibe estudiantes (Art. 15).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acuerdos_colaboracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros_practica');
            $table->enum('tipo', ['tradicional', 'con_equipamiento']);
            $table->string('representante_legal');
            $table->date('fecha_inicio')->nullable(); // vacía = en trámite
            $table->date('fecha_termino')->nullable();
            $table->string('archivo_pdf')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acuerdos_colaboracion');
    }
};
