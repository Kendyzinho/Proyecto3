<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $fillable = ['anio', 'semestre'];

    // Período en curso según la fecha (2º semestre desde julio); si no existe, el más reciente
    public static function actual(): ?self
    {
        $semestre = now()->month >= 7 ? 2 : 1;

        return static::where('anio', now()->year)->where('semestre', $semestre)->first()
            ?? static::orderByDesc('anio')->orderByDesc('semestre')->first();
    }

    // "2º semestre 2026", como se muestra en las pantallas
    public function nombre(): string
    {
        return "{$this->semestre}º semestre {$this->anio}";
    }
}
