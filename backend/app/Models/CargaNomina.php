<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaNomina extends Model
{
    protected $table = 'cargas_nomina';

    protected $fillable = ['secretaria_id', 'fecha_hora', 'archivo', 'filas_con_error'];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime'];
    }

    public function secretaria(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretaria_id');
    }

    public function habilitaciones(): HasMany
    {
        return $this->hasMany(Habilitacion::class, 'carga_id');
    }
}
