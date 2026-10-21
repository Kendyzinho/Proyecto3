<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PracticaSolicitudNotificacion extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $asunto, public string $mensaje)
    {
    }

    public function build(): self
    {
        return $this->subject($this->asunto)->view('emails.practica-solicitud');
    }
}
