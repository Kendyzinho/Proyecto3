<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// HU-14: luz roja o amarilla sobre una práctica; sin fecha_atencion sigue abierta
class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = ['practica_id', 'coordinadora_id', 'tipo', 'fecha_referencia', 'fecha_generada', 'fecha_atencion'];

    protected function casts(): array
    {
        return [
            'fecha_referencia' => 'date',
            'fecha_generada' => 'datetime',
            'fecha_atencion' => 'datetime',
        ];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    // Quién la marcó como atendida
    public function coordinadora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinadora_id');
    }
}
