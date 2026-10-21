<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ver config/mail.php: evita escribirles a los correos de los datos de prueba
        if ($direccion = config('mail.siempre_a')) {
            Mail::alwaysTo($direccion);
        }
    }
}
