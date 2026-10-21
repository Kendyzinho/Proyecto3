<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipoPractica extends Model
{
    protected $table = 'tipos_practica';

    protected $fillable = ['malla_id', 'nombre', 'categoria', 'horas_requeridas', 'prerrequisito', 'fecha_baja'];

    protected function casts(): array
    {
        return ['fecha_baja' => 'date'];
    }

    public function malla(): BelongsTo
    {
        return $this->belongsTo(Malla::class);
    }
}
