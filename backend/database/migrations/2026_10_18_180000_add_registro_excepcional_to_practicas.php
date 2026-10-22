<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practicas', function (Blueprint $table) {
            $table->text('justificacion_excepcional')->nullable();
            $table->foreignId('registrada_por')->nullable()->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('practicas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('registrada_por');
            $table->dropColumn('justificacion_excepcional');
        });
    }
};
