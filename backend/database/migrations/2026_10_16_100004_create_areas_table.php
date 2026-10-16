<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ÁREA: áreas de desempeño para cruzar estudiantes y centros (HU-08).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique(); // diseño gráfico, web, animación...
            $table->date('fecha_baja')->nullable(); // vacía = activa
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
