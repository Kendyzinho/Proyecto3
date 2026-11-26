<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * HU-13 y HU-17 usan la base local practicas, pero aíslan sus fixtures en una
 * transacción para no reiniciar ni dejar cambios permanentes en los datos.
 */
trait BaseDeDatosPracticasTransaccional
{
    protected function iniciarTransaccionEnPracticas(): void
    {
        if (! $this->app->environment('testing') || $this->app->configurationIsCached()) {
            throw new LogicException('Estas pruebas requieren APP_ENV=testing y una configuración no cacheada.');
        }

        $nombreConexion = config('database.default');
        config(["database.connections.{$nombreConexion}.database" => 'practicas']);
        DB::purge($nombreConexion);
        $conexion = DB::connection($nombreConexion);

        if ($conexion->getDatabaseName() !== 'practicas'
            || $conexion->selectOne('SELECT DATABASE() AS nombre')->nombre !== 'practicas') {
            throw new LogicException('Estas pruebas HU-13/HU-17 requieren exclusivamente la base practicas.');
        }

        $conexion->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($conexion): void {
            if ($conexion->transactionLevel() > 0) {
                $conexion->rollBack();
            }
        });
    }
}
