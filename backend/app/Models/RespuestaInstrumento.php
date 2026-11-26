<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Respuesta de un instrumento para una práctica; sin fecha_envio es un borrador
class RespuestaInstrumento extends Model
{
    protected $table = 'respuestas_instrumento';

    protected $fillable = ['plantilla_id', 'practica_id', 'visita_id', 'fecha_envio'];

    protected function casts(): array
    {
        return ['fecha_envio' => 'datetime'];
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(PlantillaInstrumento::class, 'plantilla_id');
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleRespuesta::class, 'respuesta_id');
    }

    public function enviada(): bool
    {
        return $this->fecha_envio !== null;
    }
}
