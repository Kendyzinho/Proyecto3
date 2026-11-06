<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;

// Proceso independiente para probar dos peticiones concurrentes sobre la base aislada.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$xml = simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml');
foreach ($xml->php->env as $env) {
    $nombre = (string) $env['name'];
    $valor = (string) $env['value'];
    putenv($nombre.'='.$valor);
    $_ENV[$nombre] = $_SERVER[$nombre] = $valor;
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || $app->configurationIsCached()
    || $app['db']->connection()->selectOne('SELECT DATABASE() AS nombre')->nombre !== 'practicas_test') {
    throw new RuntimeException('Proceso permitido exclusivamente en practicas_test.');
}
$entrada = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo "READY\n";
flush();
$request = Request::create('/api/coordinacion/practicas-excepcionales', 'POST', server: [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$entrada['token'],
], content: json_encode($entrada['datos'], JSON_THROW_ON_ERROR));
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo $response->getStatusCode();
$kernel->terminate($request, $response);
