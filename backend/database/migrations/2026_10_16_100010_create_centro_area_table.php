<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CENTRO_ÁREA: áreas que ofrece cada centro.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centro_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros_practica')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas');
            $table->timestamps();
            $table->unique(['centro_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_area');
    }
};
