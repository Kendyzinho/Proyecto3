<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Practica extends Model
{
    protected $table = 'practicas';

    protected $fillable = [
        'habilitacion_id', 'supervisor_id', 'origen', 'centro_propuesto_en_solicitud',
        'supervisor_propuesto_en_solicitud', 'modalidad', 'tareas', 'fecha_inicio', 'fecha_fin',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'centro_propuesto_en_solicitud' => 'boolean',
            'supervisor_propuesto_en_solicitud' => 'boolean',
        ];
    }

    public function habilitacion(): BelongsTo
    {
        return $this->belongsTo(Habilitacion::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(HistorialPractica::class)->orderByDesc('fecha_hora')->orderByDesc('id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'practica_id');
    }

    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class)->orderByDesc('fecha')->orderByDesc('id');
    }
}
