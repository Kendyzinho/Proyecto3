<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcuerdoRequest;
use App\Models\AcuerdoColaboracion;
use App\Models\CentroPractica;
use App\Models\Documento;
use App\Models\Periodo;
use App\Models\Practica;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// HU-10: documentación formal de la práctica y acuerdo de colaboración del centro
class DocumentoController extends Controller
{
    public function __construct(private readonly DocumentoService $documentos)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $periodo = Periodo::findOrFail($request->integer('periodo_id'));

        return response()->json(['data' => $this->documentos->listar($periodo)]);
    }

    public function show(Practica $practica): JsonResponse
    {
        abort_if($practica->supervisor_id === null, 404);

        return response()->json($this->documentos->detalle($practica));
    }

    // Descarga el PDF vigente de ese tipo; lo genera si todavía no existe
    public function descargar(Request $request, Practica $practica, string $tipo): StreamedResponse
    {
        abort_unless(in_array($tipo, DocumentoService::TIPOS, true), 404);
        $documento = $this->documentos->vigente($practica, $tipo, $request->user());

        return $this->archivo($documento);
    }

    public function enviar(Request $request, Practica $practica): JsonResponse
    {
        return response()->json(['destinatarios' => $this->documentos->enviar($practica, $request->user())]);
    }

    public function guardarAcuerdo(AcuerdoRequest $request, CentroPractica $centro): JsonResponse
    {
        $datos = $request->safe()->except('archivo');
        if ($request->hasFile('archivo')) {
            $datos['archivo_pdf'] = $request->file('archivo')->store("acuerdos/{$centro->id}");
        }

        // Se actualiza el acuerdo actual; si el centro no tiene, se crea
        $acuerdo = $centro->acuerdoActual() ?? new AcuerdoColaboracion(['centro_id' => $centro->id]);
        $acuerdo->fill($datos)->save();

        return response()->json(['id' => $acuerdo->id, 'estado' => $acuerdo->estado()]);
    }

    public function archivoAcuerdo(AcuerdoColaboracion $acuerdo): StreamedResponse
    {
        abort_if($acuerdo->archivo_pdf === null, 404);

        return Storage::download($acuerdo->archivo_pdf, 'Acuerdo de colaboración.pdf');
    }

    private function archivo(Documento $documento): StreamedResponse
    {
        $nombre = $documento->tipo === 'carta' ? 'Carta de presentación.pdf' : 'Guía del seguro escolar.pdf';

        return Storage::download($documento->archivo_o_url, $nombre);
    }
}
