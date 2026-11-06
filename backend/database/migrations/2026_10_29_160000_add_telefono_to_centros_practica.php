<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La migración original ya incluye teléfono en bases nuevas. Esta reparación
        // agrega la columna únicamente a bases existentes que tengan el esquema antiguo.
        if (! Schema::hasColumn('centros_practica', 'telefono')) {
            Schema::table('centros_practica', function (Blueprint $table) {
                $table->string('telefono', 30)->nullable()->after('direccion');
            });
        }
    }

    public function down(): void
    {
        // No se elimina: en instalaciones nuevas la columna pertenece a la migración
        // original y quitarla aquí rompería el esquema al revertir solo esta migración.
    }
};
