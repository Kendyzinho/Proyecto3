<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Pregunta de un instrumento: escala, única, varias o texto
class Item extends Model
{
    protected $table = 'items';

    protected $fillable = ['plantilla_id', 'clave', 'enunciado', 'tipo_respuesta', 'obligatorio', 'seccion', 'orden'];

    protected function casts(): array
    {
        return ['obligatorio' => 'boolean'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaInstrumento::class, 'plantilla_id');
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(Opcion::class, 'item_id')->orderBy('orden');
    }

    public function seRespondeConTexto(): bool
    {
        return $this->tipo_respuesta === 'texto';
    }
}
