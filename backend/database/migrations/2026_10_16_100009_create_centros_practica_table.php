<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CENTRO_PRÁCTICA: institución que recibe practicantes (HU-12).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centros_practica', function (Blueprint $table) {
            $table->id();
            $table->string('rut', 12)->nullable()->unique();
            $table->string('razon_social');
            $table->string('nombre');
            $table->string('direccion')->nullable();
            $table->boolean('es_interno')->default(false); // la UTA es un solo centro interno
            $table->smallInteger('cupos')->unsigned()->default(0); // por período
            $table->date('fecha_alta')->nullable(); // vacía = propuesto
            $table->date('fecha_baja')->nullable(); // vacía = activo
            $table->string('motivo_baja')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_practica');
    }
};
