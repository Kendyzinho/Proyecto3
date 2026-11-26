<?php

namespace App\Services;

use App\Models\Periodo;
use App\Models\Practica;
use App\Models\User;
use App\Models\VisitaTecnica;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// HU-15: la Coordinadora agenda visitas de supervisión (Art. 10 e) y ve qué prácticas siguen sin visita este semestre.
// El centro de una práctica sale de su supervisor(a).
class VisitaTecnicaService
{
    private const ESTADOS_VISITABLES = ['confirmada', 'en_curso'];

    private const RELACIONES = ['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro'];

    public function listar(Periodo $periodo): Collection
    {
        return VisitaTecnica::with(['practicas' => fn ($q) => $q->with(self::RELACIONES)])
            ->whereHas('practicas.habilitacion', fn ($q) => $q->where('periodo_id', $periodo->id))
            ->orderBy('fecha_hora')->orderBy('id')
            ->get()
            ->map(fn (VisitaTecnica $visita) => $this->serializar($visita))
            ->values();
    }

    // El reglamento pide al menos una visita por práctica: el centro sigue en la alerta mientras le quede una sin visita
    public function centrosSinVisita(Periodo $periodo): Collection
    {
        return $this->visitables($periodo)
            ->whereDoesntHave('visitas', fn ($q) => $q->where('cancelada', false))
            ->with(self::RELACIONES)->get()
            ->groupBy(fn (Practica $practica) => $practica->supervisor->centro_id)
            ->map(fn (Collection $practicas) => [
                'id' => $practicas->first()->supervisor->centro->id,
                'nombre' => $practicas->first()->supervisor->centro->nombre,
                'practicas' => $this->practicas($practicas),
            ])
            ->sortBy('nombre')->values();
    }

    public function practicasDisponibles(Periodo $periodo): Collection
    {
        return $this->visitables($periodo)->with(self::RELACIONES)->get()
            ->map(fn (Practica $practica) => $this->practica($practica) + [
                'centro' => ['id' => $practica->supervisor->centro->id, 'nombre' => $practica->supervisor->centro->nombre],
            ])
            ->sortBy('estudiante')->values();
    }

    public function crear(array $datos, User $usuario): VisitaTecnica
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $this->validarPracticas($datos['practicas'], nuevas: $datos['practicas']);
            $visita = VisitaTecnica::create([
                'coordinadora_id' => $usuario->id, 'fecha_hora' => $datos['fecha_hora'], 'modalidad' => $datos['modalidad'],
            ]);
            $visita->practicas()->sync($datos['practicas']);

            return $visita;
        });
    }

    public function editar(VisitaTecnica $visita, array $datos): VisitaTecnica
    {
        return DB::transaction(function () use ($visita, $datos) {
            $visita = VisitaTecnica::whereKey($visita->id)->lockForUpdate()->firstOrFail();
            if ($visita->cancelada) {
                throw ValidationException::withMessages(['visita' => 'Una visita cancelada no se puede editar.']);
            }

            $actuales = $visita->practicas()->pluck('practicas.id')->all();
            $nuevas = array_values(array_diff($datos['practicas'], $actuales));

            // Con pautas registradas la visita ya ocurrió: solo se pueden sumar estudiantes que también se revisaron
            if ($this->tienePautas($visita)) {
                $mismaVisita = $visita->fecha_hora->format('Y-m-d H:i') === $datos['fecha_hora'] && $visita->modalidad === $datos['modalidad'];
                if (! $mismaVisita || array_diff($actuales, $datos['practicas'])) {
                    throw ValidationException::withMessages(['visita' => 'La visita ya tiene pautas registradas: solo puedes agregar estudiantes.']);
                }
            }

            $this->validarPracticas($datos['practicas'], $nuevas);
            $visita->update(['fecha_hora' => $datos['fecha_hora'], 'modalidad' => $datos['modalidad']]);
            $visita->practicas()->sync($datos['practicas']);

            return $visita;
        });
    }

    public function cancelar(VisitaTecnica $visita): VisitaTecnica
    {
        return DB::transaction(function () use ($visita) {
            $visita = VisitaTecnica::whereKey($visita->id)->lockForUpdate()->firstOrFail();
            if ($visita->cancelada) {
                throw ValidationException::withMessages(['visita' => 'La visita ya estaba cancelada.']);
            }
            if ($this->tienePautas($visita)) {
                throw ValidationException::withMessages(['visita' => 'La visita ya tiene pautas registradas y no se puede cancelar.']);
            }
            $visita->update(['cancelada' => true]);

            return $visita;
        });
    }

    public function serializar(VisitaTecnica $visita): array
    {
        $visita->loadMissing(['practicas' => fn ($q) => $q->with(self::RELACIONES)]);
        $centro = $visita->practicas->first()?->supervisor?->centro;

        return [
            'id' => $visita->id,
            'fecha_hora' => $visita->fecha_hora->format('Y-m-d H:i'),
            'modalidad' => $visita->modalidad,
            'estado' => $visita->estado(),
            'centro' => $centro ? ['id' => $centro->id, 'nombre' => $centro->nombre] : null,
            'practicas' => $this->practicas($visita->practicas),
        ];
    }

    public function tienePautas(VisitaTecnica $visita): bool
    {
        return DB::table('respuestas_instrumento')->where('visita_id', $visita->id)->exists();
    }

    private function visitables(Periodo $periodo): Builder
    {
        return Practica::conEstado(self::ESTADOS_VISITABLES)
            ->whereNotNull('supervisor_id')
            ->whereHas('habilitacion', fn ($q) => $q->where('periodo_id', $periodo->id));
    }

    // Todas del mismo centro; el estado se exige solo a las que se agregan (una ya incluida puede haber finalizado)
    private function validarPracticas(array $ids, array $nuevas): void
    {
        $practicas = Practica::with('supervisor')->whereIn('id', $ids)->get();
        $centros = $practicas->map(fn (Practica $practica) => $practica->supervisor?->centro_id)->unique();
        if ($centros->count() !== 1 || $centros->first() === null) {
            throw ValidationException::withMessages(['practicas' => 'Todas las prácticas de una visita deben ser del mismo centro.']);
        }

        $periodo = Periodo::actual();
        $validas = $nuevas && $periodo
            ? $this->visitables($periodo)->whereIn('id', $nuevas)->count()
            : 0;
        if ($nuevas && $validas !== count($nuevas)) {
            throw ValidationException::withMessages(['practicas' => 'Solo se pueden visitar prácticas confirmadas o en curso de este semestre.']);
        }
    }

    private function practicas(Collection $practicas): array
    {
        return $practicas->map(fn (Practica $practica) => $this->practica($practica))->sortBy('estudiante')->values()->all();
    }

    private function practica(Practica $practica): array
    {
        return [
            'id' => $practica->id,
            'estudiante' => $practica->habilitacion->estudiante->usuario->name,
            'tipo_practica' => $practica->habilitacion->tipoPractica->nombre,
        ];
    }
}
