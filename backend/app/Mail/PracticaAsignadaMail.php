<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class PracticaAsignadaMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Hasta tres intentos si el servidor de correo presenta un fallo temporal. */
    public int $tries = 3;

    /** Espera creciente entre reintentos (segundos). */
    public array $backoff = [60, 300];

    public function __construct(
        public string $centro,
        public string $supervisor,
    ) {}

    public function build(): self
    {
        return $this->subject('Tu práctica profesional ha sido asignada')
            ->view('emails.practica-asignada');
    }
}
