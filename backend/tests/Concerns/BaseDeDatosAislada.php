<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;

trait BaseDeDatosAislada
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $conexion = $this->app['db']->connection();
        if (! $this->app->environment('testing')
            || $this->app->configurationIsCached()
            || $conexion->getDatabaseName() !== 'practicas_test'
            || $conexion->selectOne('SELECT DATABASE() AS nombre')->nombre !== 'practicas_test') {
            throw new LogicException('Estas pruebas requieren exclusivamente practicas_test.');
        }
    }
}
