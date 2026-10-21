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
        'justificacion_excepcional', 'registrada_por',
    ];

    public function autoraRegistro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

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

    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'practica_id');
    }
}
