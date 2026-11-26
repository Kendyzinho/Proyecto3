<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Versión de un instrumento (pauta de visita, informe confidencial, encuesta): sus ítems viven en items
class PlantillaInstrumento extends Model
{
    protected $table = 'plantillas_instrumento';

    protected $fillable = ['tipo_practica_id', 'instrumento', 'vigente_desde'];

    protected function casts(): array
    {
        return ['vigente_desde' => 'date'];
    }

    public function tipoPractica(): BelongsTo
    {
        return $this->belongsTo(TipoPractica::class, 'tipo_practica_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'plantilla_id')->orderBy('orden');
    }
}
