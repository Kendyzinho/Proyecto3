<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PERIODO: año y semestre académico.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodos', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('anio')->unsigned();
            $table->tinyInteger('semestre')->unsigned(); // 1 o 2
            $table->timestamps();
            $table->unique(['anio', 'semestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodos');
    }
};
