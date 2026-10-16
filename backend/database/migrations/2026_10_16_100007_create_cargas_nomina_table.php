<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CARGA_NÓMINA: cada planilla subida por la Secretaría (HU-07).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cargas_nomina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secretaria_id')->constrained('users');
            $table->dateTime('fecha_hora');
            $table->string('archivo');
            $table->integer('filas_con_error')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cargas_nomina');
    }
};
