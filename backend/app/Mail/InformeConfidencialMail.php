<?php

namespace App\Mail;

use App\Models\Practica;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// HU-19: la Coordinación le pide al supervisor(a) el informe confidencial de un estudiante
class InformeConfidencialMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $supervisor, public Practica $practica, public string $enlace)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Informe confidencial de práctica: '.$this->practica->habilitacion->estudiante->usuario->name);
    }

    public function content(): Content
    {
        return new Content(view: 'correos.informe-confidencial', with: [
            'nombre' => $this->supervisor->name,
            'correo' => $this->supervisor->email,
            'estudiante' => $this->practica->habilitacion->estudiante->usuario->name,
            'tipo' => $this->practica->habilitacion->tipoPractica->nombre,
        ]);
    }
}
