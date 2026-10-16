<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PLANTILLA_INSTRUMENTO: informe confidencial, encuesta al centro y pauta de visita.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plantillas_instrumento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_practica_id')->nullable()->constrained('tipos_practica'); // vacía = sirve para todos
            $table->enum('instrumento', ['informe', 'encuesta', 'pauta']);
            $table->date('vigente_desde');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plantillas_instrumento');
    }
};
