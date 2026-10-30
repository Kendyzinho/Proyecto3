<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// HU-02: enlace para definir una contraseña nueva; vence a las 24 horas
class RecuperarContrasena extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $usuario, public string $enlace)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recupera tu contraseña del Sistema de Gestión de Prácticas');
    }

    public function content(): Content
    {
        return new Content(view: 'correos.recuperar-contrasena', with: [
            'nombre' => $this->usuario->name,
            'horas' => intdiv(config('auth.passwords.users.expire'), 60),
        ]);
    }
}
