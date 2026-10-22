<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practicas', function (Blueprint $table) {
            $table->boolean('centro_propuesto_en_solicitud')->default(false)->after('origen');
            $table->boolean('supervisor_propuesto_en_solicitud')->default(false)->after('centro_propuesto_en_solicitud');
        });
    }

    public function down(): void
    {
        Schema::table('practicas', function (Blueprint $table) {
            $table->dropColumn(['centro_propuesto_en_solicitud', 'supervisor_propuesto_en_solicitud']);
        });
    }
};
