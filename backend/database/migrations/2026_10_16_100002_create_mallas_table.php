<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MALLA: plan de estudios. Hay una sola vigente a la vez.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mallas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->date('vigente_desde')->nullable(); // vacía = en preparación
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mallas');
    }
};
