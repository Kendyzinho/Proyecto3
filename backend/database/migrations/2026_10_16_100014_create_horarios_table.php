<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HORARIO: horario acordado (Art. 17), base para saber qué días se espera marca.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas')->cascadeOnDelete();
            $table->tinyInteger('dia_semana')->unsigned(); // 1 = lunes ... 7 = domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->date('vigente_desde'); // cada cambio guarda el horario completo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
