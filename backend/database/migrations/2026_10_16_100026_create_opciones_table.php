<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// OPCIÓN: alternativas de un ítem (escala 1-5, N/A, etc.).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->smallInteger('orden')->unsigned();
            $table->string('etiqueta');
            $table->decimal('valor_numerico', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opciones');
    }
};
