<?php

namespace App\Services;

use App\Models\Malla;
use App\Models\TipoPractica;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// el jefe de carrera arma las mallas y sus prácticas .
// solo a la malla en preparación se le agregan o quitan prácticas. En la vigente se pueden corregir las horas:
// los estudiantes ya habilitados conservan las horas que se copiaron al habilitarlos.
class MallaService
{
    // La vigente, la programada y la que está en preparación; las anteriores ya no se muestran
    public function listar(): Collection
    {
        return Malla::with($this->practicasActivas())
            ->orderByRaw('vigente_desde IS NULL')
            ->orderBy('vigente_desde')
            ->get()
            ->reject(fn (Malla $m) => $m->estado() === 'anterior')
            ->values();
    }

    public function crear(string $nombre): Malla
    {
        if (Malla::whereNull('vigente_desde')->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya hay una malla en preparación. Publícala antes de crear otra.']);
        }

        return Malla::create(['nombre' => Str::squish($nombre)])->load($this->practicasActivas());
    }

    public function publicar(Malla $malla, string $fecha): Malla
    {
        if ($malla->vigente_desde !== null) {
            throw ValidationException::withMessages(['vigente_desde' => 'La malla ya está publicada.']);
        }
        if (! $malla->tiposPractica()->whereNull('fecha_baja')->exists()) {
            throw ValidationException::withMessages(['vigente_desde' => 'Agrega al menos una práctica antes de publicar la malla.']);
        }

        // Las mallas se suceden en el tiempo: la nueva empieza después de cualquier otra ya publicada
        $ultima = Malla::whereNotNull('vigente_desde')->max('vigente_desde');
        if ($ultima !== null && $fecha <= $ultima) {
            throw ValidationException::withMessages([
                'vigente_desde' => 'La fecha debe ser posterior al '.Carbon::parse($ultima)->format('d-m-Y').'.',
            ]);
        }

        $malla->update(['vigente_desde' => $fecha]);

        return $malla->load($this->practicasActivas());
    }

    public function agregarPractica(Malla $malla, array $datos): TipoPractica
    {
        $this->exigirEnPreparacion($malla);

        return $malla->tiposPractica()->create($datos);
    }

    public function editarPractica(TipoPractica $tipo, array $datos): TipoPractica
    {
        if ($tipo->malla->estado() === 'anterior') {
            throw ValidationException::withMessages(['nombre' => 'Esta malla ya fue reemplazada y no se puede modificar.']);
        }

        $tipo->update($datos);

        return $tipo;
    }

    public function quitarPractica(TipoPractica $tipo): void
    {
        $this->exigirEnPreparacion($tipo->malla);

        // Nadie se habilita en una malla en preparación, así que la práctica se puede borrar
        $tipo->delete();
    }

    private function exigirEnPreparacion(Malla $malla): void
    {
        if ($malla->vigente_desde !== null) {
            throw ValidationException::withMessages(['nombre' => 'La malla ya está publicada: no se pueden agregar ni quitar prácticas.']);
        }
    }

    private function practicasActivas(): array
    {
        return ['tiposPractica' => fn ($q) => $q->whereNull('fecha_baja')->orderBy('id')];
    }
}
