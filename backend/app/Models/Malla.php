<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Malla extends Model
{
    protected $fillable = ['nombre', 'vigente_desde'];

    protected function casts(): array
    {
        return ['vigente_desde' => 'date'];
    }

    public function tiposPractica(): HasMany
    {
        return $this->hasMany(TipoPractica::class);
    }

    // La malla que rige hoy: la más reciente con vigente_desde cumplido (vacia = en preparación)
    public static function vigente(): ?self
    {
        return static::query()
            ->whereNotNull('vigente_desde')
            ->whereDate('vigente_desde', '<=', now())
            ->orderByDesc('vigente_desde')
            ->first();
    }
        //  en_preparacion (sin fecha), programada (fecha futura), vigente (la que rige hoy) o anterior (ya reemplazada)
    public function estado(): string
    {
        if ($this->vigente_desde === null) {
            return 'en_preparacion';
        }
        if ($this->vigente_desde->isFuture()) {
            return 'programada';
        }

        return static::vigente()?->is($this) ? 'vigente' : 'anterior';
    }
}
