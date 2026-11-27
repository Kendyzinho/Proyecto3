<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcuerdoRequest;
use App\Http\Requests\BorradorAcuerdoRequest;
use App\Models\AcuerdoColaboracion;
use App\Models\CentroPractica;
use App\Models\Documento;
use App\Models\Periodo;
use App\Models\Practica;
use App\Services\DocumentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
        $this->exigirFormalizada($practica);

        return response()->json($this->documentos->detalle($practica));
    }

    // Descarga el PDF vigente de ese tipo; lo genera si todavía no existe
    public function descargar(Request $request, Practica $practica, string $tipo): StreamedResponse
    {
        abort_unless(in_array($tipo, DocumentoService::TIPOS, true), 404);
        $this->exigirFormalizada($practica);
        $documento = $this->documentos->vigente($practica, $tipo, $request->user());

        return $this->archivo($documento);
    }

    public function enviar(Request $request, Practica $practica): JsonResponse
    {
        $this->exigirFormalizada($practica);

        return response()->json(['destinatarios' => $this->documentos->enviar($practica, $request->user())]);
    }

    public function guardarAcuerdo(AcuerdoRequest $request, CentroPractica $centro): JsonResponse
    {
        $datos = $request->safe()->except('archivo');
        if ($request->hasFile('archivo')) {
            $datos['archivo_pdf'] = $request->file('archivo')->store("acuerdos/{$centro->id}");
        }

        // Se corrige el último acuerdo mientras no rija (en trámite o firmado para más adelante).
        // Uno vigente o vencido no se toca: lo firmado queda como historial y se registra uno nuevo (renovación)
        $ultimo = $centro->acuerdoActual();
        $acuerdo = $ultimo?->esEditable() ? $ultimo : new AcuerdoColaboracion(['centro_id' => $centro->id]);
        DB::transaction(function () use ($acuerdo, $datos, $centro) {
            $acuerdo->fill($datos)->save();

            // HU-10 formaliza centros externos al cargar un acuerdo firmado y vigente.
            // La fecha de inicio controla cuándo entra en vigor; la UTA está exenta.
            $fechaInicio = $acuerdo->fecha_inicio;
            $vigenciaNoTerminada = $acuerdo->fecha_termino === null
                || $acuerdo->fecha_termino->greaterThanOrEqualTo(today());
            $fechaAltaCentro = $centro->fresh()->fecha_alta;
            $altaPendiente = $fechaAltaCentro === null || $fechaAltaCentro->isFuture();
            if (!$centro->es_interno && $altaPendiente) {
                $fechaAlta = $fechaInicio && $vigenciaNoTerminada && $acuerdo->archivo_pdf
                    ? $fechaInicio->toDateString()
                    : null;
                CentroPractica::query()->whereKey($centro->id)->update([
                    'fecha_alta' => $fechaAlta,
                    'updated_at' => now(),
                ]);
            }
        });

        return response()->json(['id' => $acuerdo->id, 'estado' => $acuerdo->estado()]);
    }

    // Acuerdo lleno con el formato oficial, para que el centro y el Jefe de Carrera lo firmen
    public function borradorAcuerdo(BorradorAcuerdoRequest $request, CentroPractica $centro): Response
    {
        abort_if($centro->es_interno, 422, 'Un centro interno no necesita acuerdo de colaboración.');
        $datos = $request->validated();
        $pdf = $this->documentos->generarAcuerdo(
            $centro, $datos['tipo'], $datos['representante_legal'], Carbon::parse($datos['fecha_inicio']), Carbon::parse($datos['fecha_termino']),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Acuerdo de colaboración.pdf"',
        ]);
    }

    public function archivoAcuerdo(AcuerdoColaboracion $acuerdo): StreamedResponse
    {
        abort_if($acuerdo->archivo_pdf === null, 404);

        return Storage::response(
            $acuerdo->archivo_pdf,
            'Acuerdo de colaboración.pdf',
            ['Content-Type' => 'application/pdf'],
            'inline',
        );
    }

    // Una solicitud por revisar o devuelta todavía no tiene documentos formales
    private function exigirFormalizada(Practica $practica): void
    {
        abort_unless($practica->supervisor_id !== null && $practica->estaFormalizada(), 404);
    }

    private function archivo(Documento $documento): StreamedResponse
    {
        $nombre = $documento->tipo === 'carta' ? 'Carta de presentación.pdf' : 'Guía del seguro escolar.pdf';

        return Storage::download($documento->archivo_o_url, $nombre);
    }
}
