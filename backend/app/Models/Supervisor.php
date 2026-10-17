<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Persona del centro que guía al estudiante (Supervisor(a) de Prácticas, Art. 5)
class Supervisor extends Model
{
    protected $table = 'supervisores';

    protected $fillable = ['centro_id', 'nombre', 'correo', 'telefono', 'cargo', 'unidad', 'fecha_alta', 'fecha_baja'];

    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date',
            'fecha_baja' => 'date',
        ];
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(CentroPractica::class, 'centro_id');
    }
}
