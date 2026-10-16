<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// REGISTRO_AUDITORÍA: quién cambió qué y cuándo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('supervisor_id')->nullable()->constrained('supervisores');
            $table->dateTime('fecha_hora');
            $table->enum('accion', ['crear', 'editar']);
            $table->string('tabla_afectada', 60);
            $table->unsignedBigInteger('id_afectado');
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_auditoria');
    }
};
