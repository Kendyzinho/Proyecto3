<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// VISITA_TÉCNICA: una visita puede cubrir varias prácticas (HU-15).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitas_tecnicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coordinadora_id')->constrained('users');
            $table->dateTime('fecha_hora');
            $table->enum('modalidad', ['terreno', 'virtual']);
            $table->boolean('cancelada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitas_tecnicas');
    }
};
