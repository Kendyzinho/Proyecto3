<?php

namespace App\Services;

use App\Mail\InformeConfidencialMail;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\RespuestaInstrumento;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

// HU-19: la Coordinadora (o el Jefe) pide el informe confidencial; el supervisor(a) lo responde con su cuenta.
// Formulario oficial 2026 por tipo de práctica (plantilla `informe`); lo guarda InstrumentoService.
// Confidencial: ninguna ruta del estudiante lo expone.
class InformeConfidencialService
{
    private const INSTRUMENTO = 'informe';

    private const RELACIONES = ['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro'];

    public function __construct(
        private readonly InstrumentoService $instrumentos,
        private readonly CuentaSupervisorService $cuentas,
    ) {}

    // Incluye las finalizadas: el informe se pide casi siempre al terminar la práctica
    public function listar(Periodo $periodo): Collection
    {
        $practicas = Practica::formalizadas()
            ->whereHas('habilitacion', fn ($q) => $q->where('periodo_id', $periodo->id))
            ->with(self::RELACIONES)->get();
        $informes = $this->informes($practicas->pluck('id'));

        return $practicas
            ->map(fn (Practica $practica) => $this->fila($practica, $informes->get($practica->id)))
            ->sortBy('estudiante')->values();
    }

    /** @return array{0: array, 1: string} la fila actualizada y el mensaje para la Coordinadora */
    public function solicitar(Practica $practica): array
    {
        [$respuesta, $invitar] = DB::transaction(function () use ($practica) {
            $practica = Practica::whereKey($practica->id)->lockForUpdate()->with(self::RELACIONES)->firstOrFail();
            abort_unless($practica->estaFormalizada(), 404);

            $supervisor = $practica->supervisor;
            if (! $supervisor || ! $supervisor->estaActivo()) {
                throw ValidationException::withMessages(['practica' => 'La práctica no tiene un supervisor(a) activo que pueda responder el informe.']);
            }
            $existente = $this->instrumentos->respuestaDe($practica->id, self::INSTRUMENTO);
            if ($existente?->enviada()) {
                throw ValidationException::withMessages(['practica' => 'El supervisor(a) ya envió este informe.']);
            }

            $plantilla = $existente?->plantilla
                ?? $this->instrumentos->plantillaVigente(self::INSTRUMENTO, $practica->habilitacion->tipo_practica_id);
            if (! $plantilla) {
                throw ValidationException::withMessages(['practica' => 'No hay un informe confidencial configurado para este tipo de práctica.']);
            }

            // Los supervisores activados antes de las cuentas reciben la suya ahora
            $invitar = $this->cuentas->asegurar($supervisor);

            return [$existente ?? $this->instrumentos->crearRespuesta($practica->id, $plantilla), $invitar];
        });

        $practica->load(self::RELACIONES);
        $practica->supervisor->load('usuario');
        if ($invitar) {
            $this->cuentas->invitar($invitar);
        }
        $avisado = $this->avisar($practica);

        $mensaje = $avisado
            ? "Le pedimos el informe a {$practica->supervisor->nombre}."
            : 'El informe quedó solicitado, pero no se pudo enviar el correo. Inténtalo de nuevo con "Reenviar aviso".';

        return [$this->fila($practica, $respuesta->fresh()), $mensaje];
    }

    // Solo lectura para la Coordinación: el borrador del supervisor no se muestra
    public function enviado(Practica $practica): array
    {
        $respuesta = $this->instrumentos->respuestaDe($practica->id, self::INSTRUMENTO);
        abort_unless($respuesta?->enviada(), 404, 'El informe todavía no ha sido enviado.');

        return $this->detalle($practica, $respuesta);
    }

    public function delSupervisor(User $supervisor): Collection
    {
        $practicas = Practica::whereHas('supervisor', fn ($q) => $q->where('user_id', $supervisor->id))
            ->with(self::RELACIONES)->get();
        $informes = $this->informes($practicas->pluck('id'));

        return $practicas
            ->filter(fn (Practica $practica) => $informes->has($practica->id))
            ->map(fn (Practica $practica) => $this->fila($practica, $informes->get($practica->id)))
            // Primero lo que falta responder; los enviados al final
            ->sortBy([
                fn ($a, $b) => ($a['estado'] === 'enviado') <=> ($b['estado'] === 'enviado'),
                fn ($a, $b) => strcmp($a['estudiante'], $b['estudiante']),
            ])->values();
    }

    public function paraSupervisor(User $supervisor, Practica $practica): array
    {
        return $this->detalle($practica, $this->deSuPractica($supervisor, $practica));
    }

    public function guardar(User $supervisor, Practica $practica, array $respuestas, bool $enviar): array
    {
        $respuesta = $this->instrumentos->guardar($this->deSuPractica($supervisor, $practica), $respuestas, $enviar);

        return $this->detalle($practica, $respuesta);
    }

    // 404 si la práctica no es de este supervisor(a) o no le han pedido el informe
    private function deSuPractica(User $supervisor, Practica $practica): RespuestaInstrumento
    {
        $practica->loadMissing('supervisor');
        abort_unless($practica->supervisor?->user_id === $supervisor->id, 404);

        $respuesta = $this->instrumentos->respuestaDe($practica->id, self::INSTRUMENTO);
        abort_unless($respuesta !== null, 404);

        return $respuesta;
    }

    private function detalle(Practica $practica, RespuestaInstrumento $respuesta): array
    {
        $practica->loadMissing(self::RELACIONES);

        return [
            'practica' => $this->practica($practica),
            'informe' => $this->instrumentos->leer($respuesta),
        ];
    }

    private function avisar(Practica $practica): bool
    {
        $cuenta = $practica->supervisor->usuario;
        try {
            $enlace = rtrim(config('app.frontend_url'), '/')."/supervisor/informes/{$practica->id}";
            Mail::to($cuenta)->send(new InformeConfidencialMail($cuenta, $practica, $enlace));

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar el informe confidencial', ['practica_id' => $practica->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    // Informe (respuesta) de cada práctica, con la cantidad de respuestas para distinguir solicitado de borrador
    private function informes(Collection $practicaIds): Collection
    {
        return RespuestaInstrumento::whereIn('practica_id', $practicaIds)
            ->whereNull('visita_id')
            ->whereHas('plantilla', fn ($q) => $q->where('instrumento', self::INSTRUMENTO))
            ->withCount('detalles')
            ->get()->keyBy('practica_id');
    }

    private function fila(Practica $practica, ?RespuestaInstrumento $respuesta): array
    {
        $estado = match (true) {
            $respuesta === null => 'sin_solicitar',
            $respuesta->enviada() => 'enviado',
            ($respuesta->detalles_count ?? $respuesta->detalles()->count()) > 0 => 'borrador',
            default => 'solicitado',
        };

        return $this->practica($practica) + [
            'estado' => $estado,
            'solicitado_en' => $respuesta?->created_at?->toIso8601String(),
            'enviado_en' => $respuesta?->fecha_envio?->toIso8601String(),
        ];
    }

    private function practica(Practica $practica): array
    {
        return [
            'id' => $practica->id,
            'estudiante' => $practica->habilitacion->estudiante->usuario->name,
            'tipo_practica' => $practica->habilitacion->tipoPractica->nombre,
            'centro' => $practica->supervisor?->centro?->nombre,
            'supervisor' => $practica->supervisor?->nombre,
            'fecha_fin' => $practica->fecha_fin?->toDateString(),
        ];
    }
}
