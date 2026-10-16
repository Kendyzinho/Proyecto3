<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PRÁCTICA: el estado no se guarda, sale del historial, el dictamen y el acta.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habilitacion_id')->constrained('habilitaciones');
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisores'); // el centro sale del supervisor
            $table->enum('origen', ['solicitud', 'asignacion', 'excepcional']);
            $table->enum('modalidad', ['presencial', 'a_distancia', 'mixta']);
            $table->text('tareas')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practicas');
    }
};
