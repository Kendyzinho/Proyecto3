<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $fillable = ['anio', 'semestre'];

    // "2º semestre 2026", como se muestra en las pantallas
    public function nombre(): string
    {
        return "{$this->semestre}º semestre {$this->anio}";
    }
}
