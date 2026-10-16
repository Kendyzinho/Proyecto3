<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HABILITACIÓN: estudiante + tipo de práctica + período (HU-07).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habilitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carga_id')->nullable()->constrained('cargas_nomina'); // vacía = ingreso manual
            $table->foreignId('estudiante_id')->constrained('estudiantes');
            $table->foreignId('tipo_practica_id')->constrained('tipos_practica');
            $table->foreignId('periodo_id')->constrained('periodos');
            $table->boolean('cumple_prerrequisito')->default(false);
            $table->smallInteger('horas_requeridas')->unsigned(); // copia del tipo al habilitar
            $table->date('fecha_induccion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->unique(['estudiante_id', 'tipo_practica_id', 'periodo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habilitaciones');
    }
};
