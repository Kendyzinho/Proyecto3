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

    // La malla que rige hoy: la más reciente con vigente_desde cumplido (vacía = en preparación)
    public static function vigente(): ?self
    {
        return static::query()
            ->whereNotNull('vigente_desde')
            ->whereDate('vigente_desde', '<=', now())
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
