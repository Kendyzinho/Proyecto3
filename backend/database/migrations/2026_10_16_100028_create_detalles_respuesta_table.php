<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DETALLE_RESPUESTA: respuesta a cada ítem.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalles_respuesta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('respuesta_id')->constrained('respuestas_instrumento')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->foreignId('opcion_id')->nullable()->constrained('opciones');
            $table->text('texto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalles_respuesta');
    }
};
