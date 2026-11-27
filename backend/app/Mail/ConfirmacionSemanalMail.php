<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// HU-26 aviso del lunes,el supervisor confirma entrando con su cuenta
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

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Confirma la asistencia de {$this->estudiante}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.confirmacion-semanal');
    }
}