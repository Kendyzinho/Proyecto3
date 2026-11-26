<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroDiario extends Model
{
    protected $table = 'registros_diarios';

    protected $fillable = [
        'practica_id', 'fecha', 'hora_entrada', 'hora_salida', 'actividades',
        'validacion', 'justificacion', 'fecha_justificacion', 'recuperacion_programada',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_justificacion' => 'datetime',
            'recuperacion_programada' => 'date',
        ];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class, 'practica_id');
    }
}
