<?php

use App\Services\AlertaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// HU-14: revisa las prácticas en curso y crea las alertas tempranas
Artisan::command('alertas:generar', function (AlertaService $alertas) {
    $this->info("Alertas nuevas: {$alertas->generar()}");
})->purpose('Genera las alertas de abandono, inasistencia y semanas sin confirmar');

// Una vez al día, antes de que empiece la jornada de la Coordinadora
Schedule::command('alertas:generar')->dailyAt('06:00');
