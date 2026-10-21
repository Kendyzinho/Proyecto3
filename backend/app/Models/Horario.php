<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Horario extends Model
{
    protected $table = 'horarios';

    protected $fillable = ['practica_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'vigente_desde'];

    protected function casts(): array
    {
        return ['vigente_desde' => 'date'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'practica_id');
    }
}
