<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HISTORIAL_PRÁCTICA: solo decisiones humanas (aprobar, devolver, suspender...).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historiales_practica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->foreignId('user_id')->constrained('users'); // quién tomó la decisión
            $table->dateTime('fecha_hora');
            $table->string('estado_nuevo', 40);
            $table->text('motivo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historiales_practica');
    }
};
