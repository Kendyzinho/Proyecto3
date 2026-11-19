<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfirmacionSemanal extends Model
{
    protected $table = 'confirmaciones_semanales';

    protected $fillable = ['practica_id', 'semana', 'supervisor_id', 'fecha_hora_envio', 'observacion'];

    protected function casts(): array
    {
        return ['semana' => 'date', 'fecha_hora_envio' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }
}
