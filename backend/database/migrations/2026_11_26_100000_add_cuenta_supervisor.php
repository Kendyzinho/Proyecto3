<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// HU-19: el profe pidió que el supervisor(a) sea un usuario externo con cuenta propia, no un invitado con enlace.
// Una persona puede supervisar en dos centros (dos registros): por eso user_id no es único.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY rol ENUM('secretaria', 'coordinadora', 'jefe', 'supervisor') NULL");

        Schema::table('supervisores', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('centro_id')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('supervisores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        // MariaDB rechaza quitar un valor del enum mientras haya filas que lo usen
        DB::table('users')->where('rol', 'supervisor')->delete();
        DB::statement("ALTER TABLE users MODIFY rol ENUM('secretaria', 'coordinadora', 'jefe') NULL");
    }
};
