<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// HU-12: cupos definidos por centro, período y categoría.
return new class extends Migration
{
    public function up(): void
    {
        // Cupos no pertenecen al centro de forma global: cambian por período y categoría.
        if (Schema::hasColumn('centros_practica', 'cupos')) {
            Schema::table('centros_practica', fn (Blueprint $table) => $table->dropColumn('cupos'));
        }
        if (!Schema::hasColumn('centros_practica', 'telefono')) {
            Schema::table('centros_practica', fn (Blueprint $table) => $table->string('telefono', 30)->nullable()->after('direccion'));
        }

        Schema::create('requerimientos_centro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centro_id')->constrained('centros_practica');
            $table->foreignId('periodo_id')->constrained('periodos');
            $table->enum('categoria', ['laboral', 'profesional']);
            $table->unsignedSmallInteger('cupos')->default(0);
            $table->timestamps();
            $table->unique(['centro_id', 'periodo_id', 'categoria'], 'requerimiento_centro_periodo_categoria_unique');
        });

        Schema::create('requerimiento_area', function (Blueprint $table) {
            $table->foreignId('requerimiento_id')->constrained('requerimientos_centro')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('areas');
            $table->primary(['requerimiento_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requerimiento_area');
        Schema::dropIfExists('requerimientos_centro');
        if (!Schema::hasColumn('centros_practica', 'cupos')) {
            Schema::table('centros_practica', fn (Blueprint $table) => $table->unsignedSmallInteger('cupos')->default(0));
        }
        if (Schema::hasColumn('centros_practica', 'telefono')) {
            Schema::table('centros_practica', fn (Blueprint $table) => $table->dropColumn('telefono'));
        }
    }
};
