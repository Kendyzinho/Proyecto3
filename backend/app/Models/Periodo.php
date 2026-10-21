<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periodo extends Model
{
    protected $fillable = ['anio', 'semestre'];

    public function requerimientosCentro(): HasMany
    {
        return $this->hasMany(RequerimientoCentro::class);
    }
}
