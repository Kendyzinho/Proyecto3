<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ALERTA: luces rojas para la Coordinadora (HU-14).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->foreignId('coordinadora_id')->nullable()->constrained('users'); // quién la atendió
            $table->enum('tipo', ['abandono', 'inasistencia', 'sin_confirmar']);
            $table->dateTime('fecha_generada');
            $table->dateTime('fecha_atencion')->nullable(); // vacía = abierta
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
    }
};
