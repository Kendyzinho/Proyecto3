<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DOCUMENTO: cartas, seguro y enlace del portafolio (HU-10, HU-21).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practica_id')->constrained('practicas');
            $table->foreignId('user_id')->constrained('users'); // quién lo generó o registró
            $table->string('tipo', 30); // carta, seguro, portafolio...
            $table->string('archivo_o_url');
            $table->dateTime('fecha');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos');
    }
};
