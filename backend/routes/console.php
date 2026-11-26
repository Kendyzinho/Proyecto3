<?php

use App\Services\AlertaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;       
use App\Services\ConfirmacionSemanalService;
use Illuminate\Support\Facades\Schedule;


// HU-26: enlace al supervisor (por defecto, la semana anterior)
Artisan::command('supervision:enviar-enlaces {--semana= : Lunes de la semana (Y-m-d)}', function (ConfirmacionSemanalService $service) {
    $lunes = $this->option('semana') ? Carbon::parse($this->option('semana')) : now()->subWeek();
    
    $this->info('Enlaces enviados: '.$service->enviarEnlaces($lunes));
})->purpose('Envía a cada supervisor el enlace para confirmar la asistencia de la semana');

// HU-26: alerta a la Coordinadora si el supervisor no responde el enlace en una semana
Artisan::command('supervision:alertar-sin-confirmar', function (ConfirmacionSemanalService $service) {
    $this->info('Alertas nuevas: '.$service->alertarSinConfirmar());
})->purpose('Genera la alerta de las prácticas cuyo supervisor no confirmó la semana a tiempo');

Schedule::command('supervision:enviar-enlaces')->weeklyOn(1, '08:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// HU-14: revisa las prácticas en curso y crea las alertas tempranas
Artisan::command('alertas:generar', function (AlertaService $alertas) {
    $this->info("Alertas nuevas: {$alertas->generar()}");
})->purpose('Genera las alertas de abandono, inasistencia y semanas sin confirmar');

// Una vez al día, antes de que empiece la jornada de la Coordinadora
Schedule::command('alertas:generar')->dailyAt('06:00');
