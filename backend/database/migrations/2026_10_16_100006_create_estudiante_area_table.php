<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ESTUDIANTE_ÁREA: áreas de interés de cada estudiante.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudiante_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas');
            $table->timestamps();
            $table->unique(['estudiante_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudiante_area');
    }
};
