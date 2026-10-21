<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supervisor extends Model
{
    protected $table = 'supervisores';

    protected $fillable = ['centro_id', 'nombre', 'correo', 'telefono', 'cargo', 'unidad', 'fecha_alta', 'fecha_baja'];

    protected function casts(): array
    {
        return ['fecha_alta' => 'date', 'fecha_baja' => 'date'];
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(CentroPractica::class, 'centro_id');
    }

    public function practicas(): HasMany
    {
        return $this->hasMany(Practica::class, 'supervisor_id');
    }
}
