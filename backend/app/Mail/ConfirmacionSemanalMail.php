<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmacionSemanalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(
        public string $supervisor,
        public string $estudiante,
        public string $semana,
        public string $enlace,
    ) {}

    public function build(): self
    {
        return $this->subject("Confirma la asistencia de {$this->estudiante}")
            ->view('emails.confirmacion-semanal');
    }
}
