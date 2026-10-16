<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DICTAMEN: decisión final de la Coordinadora (HU-22).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictamenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->foreignId('coordinadora_id')->constrained('users');
            $table->foreignId('acta_id')->nullable()->constrained('actas'); // vacía = sin visar
            $table->enum('resultado', ['aprobado', 'reprobado']);
            $table->text('motivo')->nullable(); // obligatorio al reprobar
            $table->dateTime('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictamenes');
    }
};
