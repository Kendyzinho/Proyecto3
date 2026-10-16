<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ÍTEM: pregunta de una plantilla.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->constrained('plantillas_instrumento')->cascadeOnDelete();
            $table->string('clave', 60); // igual entre versiones, para comparar en estadísticas
            $table->text('enunciado');
            $table->enum('tipo_respuesta', ['escala', 'unica', 'varias', 'texto']);
            $table->boolean('obligatorio')->default(true);
            $table->string('seccion', 60)->nullable(); // transversal, disciplinar...
            $table->smallInteger('orden')->unsigned();
            $table->timestamps();
            $table->unique(['plantilla_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
