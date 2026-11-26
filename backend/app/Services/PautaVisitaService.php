<?php

namespace App\Services;

use App\Models\Practica;
use App\Models\RespuestaInstrumento;
use App\Models\VisitaTecnica;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// HU-16: después de la visita, la Coordinadora registra una pauta por cada práctica revisada.
// Guardar y leer las respuestas lo hace InstrumentoService (plantilla `pauta`: 6 criterios 1-5 + comentario).
class PautaVisitaService
{
    private const INSTRUMENTO = 'pauta';

    public function __construct(
        private readonly InstrumentoService $instrumentos,
        private readonly VisitaTecnicaService $visitas,
    ) {}

    public function pautas(VisitaTecnica $visita): array
    {
        $visita->loadMissing('practicas.habilitacion.estudiante.usuario', 'practicas.habilitacion.tipoPractica');
        $plantilla = $this->instrumentos->plantillaVigente(self::INSTRUMENTO, null);

        $practicas = $visita->practicas->map(function (Practica $practica) use ($visita) {
            $respuesta = $this->instrumentos->respuestaDe($practica->id, self::INSTRUMENTO, $visita->id);

            return [
                'id' => $practica->id,
                'estudiante' => $practica->habilitacion->estudiante->usuario->name,
                'tipo_practica' => $practica->habilitacion->tipoPractica->nombre,
                // Una pauta ya empezada se lee con su propia plantilla, aunque después cambie la vigente
                'respuesta' => $respuesta ? $this->instrumentos->leer($respuesta) : null,
            ];
        })->sortBy('estudiante')->values()->all();

        return [
            'visita' => $this->visitas->serializar($visita),
            'formulario' => $plantilla ? $this->instrumentos->formulario($plantilla) : null,
            'practicas' => $practicas,
        ];
    }

    public function guardar(VisitaTecnica $visita, Practica $practica, array $respuestas, bool $enviar): array
    {
        $respuesta = DB::transaction(function () use ($visita, $practica, $respuestas, $enviar) {
            // Primero la visita: así no se puede cancelar mientras se guarda su primera pauta (cancelar la bloquea igual)
            $visita = VisitaTecnica::whereKey($visita->id)->lockForUpdate()->firstOrFail();
            if ($visita->cancelada) {
                throw ValidationException::withMessages(['visita' => 'La visita fue cancelada: no se puede registrar su pauta.']);
            }
            if ($visita->fecha_hora->isFuture()) {
                throw ValidationException::withMessages(['visita' => 'La pauta se registra después de la visita.']);
            }
            abort_unless($visita->practicas()->whereKey($practica->id)->exists(), 404, 'La práctica no está en esta visita.');

            $existente = $this->instrumentos->respuestaDe($practica->id, self::INSTRUMENTO, $visita->id);
            if (! $existente) {
                $plantilla = $this->instrumentos->plantillaVigente(self::INSTRUMENTO, null);
                if (! $plantilla) {
                    throw ValidationException::withMessages(['visita' => 'No hay una pauta de visita configurada.']);
                }
                $existente = $this->instrumentos->crearRespuesta($practica->id, $plantilla, $visita->id);
            }

            return $this->instrumentos->guardar($existente, $respuestas, $enviar);
        });

        return $this->instrumentos->leer($respuesta);
    }

    // Pautas enviadas de una práctica, para su ficha (HU-17)
    public function enviadasDe(Practica $practica): array
    {
        return RespuestaInstrumento::with('plantilla', 'detalles')
            ->where('practica_id', $practica->id)
            ->whereNotNull('visita_id')
            ->whereNotNull('fecha_envio')
            ->whereHas('plantilla', fn ($q) => $q->where('instrumento', self::INSTRUMENTO))
            ->orderBy('fecha_envio')
            ->get()
            ->map(function (RespuestaInstrumento $respuesta) {
                $visita = VisitaTecnica::findOrFail($respuesta->visita_id);

                return [
                    'visita' => ['id' => $visita->id, 'fecha_hora' => $visita->fecha_hora->format('Y-m-d H:i'), 'modalidad' => $visita->modalidad],
                ] + $this->instrumentos->leer($respuesta);
            })
            ->values()->all();
    }
}
