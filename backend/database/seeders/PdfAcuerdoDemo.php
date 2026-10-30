<?php

namespace Database\Seeders;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

// Un acuerdo solo queda vigente con su PDF firmado; los datos de demo comparten este archivo de ejemplo
class PdfAcuerdoDemo
{
    public const RUTA = 'acuerdos/demo/acuerdo-colaboracion-demo.pdf';

    public static function ruta(): string
    {
        if (!Storage::exists(self::RUTA)) {
            Storage::put(self::RUTA, Pdf::loadHTML('<h1>Acuerdo de colaboración</h1><p>Documento de ejemplo para la demo.</p>')->output());
        }

        return self::RUTA;
    }
}
