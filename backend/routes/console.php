<?php

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

Schedule::command('supervision:enviar-enlaces')->weeklyOn(1, '08:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
