<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = ['practica_id', 'coordinadora_id', 'tipo', 'fecha_generada', 'fecha_atencion'];

    protected function casts(): array
    {
        return ['fecha_generada' => 'datetime', 'fecha_atencion' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }
}
