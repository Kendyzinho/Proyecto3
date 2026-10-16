<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SUPERVISOR: persona del centro. No tiene cuenta, responde desde enlaces (HU-19, HU-26).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros_practica');
            $table->string('nombre');
            $table->string('correo');
            $table->string('telefono', 30)->nullable();
            $table->string('cargo')->nullable();
            $table->string('unidad')->nullable(); // dirección o área dentro del centro
            $table->date('fecha_alta')->nullable(); // vacía = propuesto
            $table->date('fecha_baja')->nullable(); // vacía = activo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisores');
    }
};
