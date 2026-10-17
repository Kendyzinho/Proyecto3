<?php

namespace App\Mail;

use App\Models\Documento;
use App\Models\Practica;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// HU-10: carta de presentación y guía del seguro escolar para el estudiante y el centro
class DocumentosPractica extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<Documento>  $documentos
     */
    public function __construct(public Practica $practica, public array $documentos)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Documentos de práctica: '.$this->practica->habilitacion->estudiante->usuario->name);
    }

    public function content(): Content
    {
        return new Content(view: 'correos.documentos', with: [
            'estudiante' => $this->practica->habilitacion->estudiante->usuario->name,
            'practica' => $this->practica->habilitacion->tipoPractica->nombre,
            'centro' => $this->practica->supervisor->centro->nombre,
        ]);
    }

    public function attachments(): array
    {
        $nombres = ['carta' => 'Carta de presentación.pdf', 'seguro' => 'Guía del seguro escolar.pdf'];

        return array_map(
            fn (Documento $d) => Attachment::fromStorage($d->archivo_o_url)->as($nombres[$d->tipo])->withMime('application/pdf'),
            $this->documentos,
        );
    }
}
