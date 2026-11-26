<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// HU-19: al activarse en un centro, el supervisor(a) recibe su cuenta y define su contraseña (broker de HU-02)
class InvitacionSupervisorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $usuario, public string $enlace)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu cuenta en el Sistema de Gestión de Prácticas');
    }

    public function content(): Content
    {
        return new Content(view: 'correos.invitacion-supervisor', with: [
            'nombre' => $this->usuario->name,
            'correo' => $this->usuario->email,
            'horas' => intdiv(config('auth.passwords.users.expire'), 60),
            'acceso' => rtrim(config('app.frontend_url'), '/').'/auth',
        ]);
    }
}
