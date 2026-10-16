<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// VISITA_PRÁCTICA: prácticas revisadas en cada visita.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visita_practica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visita_id')->constrained('visitas_tecnicas')->cascadeOnDelete();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->timestamps();
            $table->unique(['visita_id', 'practica_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visita_practica');
    }
};
