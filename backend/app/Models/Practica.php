<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Practica extends Model
{
    // Estados de una práctica ya aprobada: ocupan cupo y pueden tener documentos formales
    public const ESTADOS_FORMALIZADOS = ['confirmada', 'en_curso', 'finalizada'];

    protected $table = 'practicas';

    protected $fillable = [
        'habilitacion_id', 'supervisor_id', 'origen', 'centro_propuesto_en_solicitud',
        'supervisor_propuesto_en_solicitud', 'modalidad', 'tareas', 'fecha_inicio', 'fecha_fin',
        'justificacion_excepcional', 'registrada_por',
    ];

    // El estado de una práctica es la última fila de su historial
    public function scopeConEstado(Builder $consulta, array $estados): Builder
    {
        $marcas = implode(', ', array_fill(0, count($estados), '?'));

        return $consulta->whereRaw(
            "(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = practicas.id order by hp.fecha_hora desc, hp.id desc limit 1) in ({$marcas})",
            $estados,
        );
    }

    public function scopeFormalizadas(Builder $consulta): Builder
    {
        return $consulta->conEstado(self::ESTADOS_FORMALIZADOS);
    }

    public function estaFormalizada(): bool
    {
        return in_array($this->historiales()->value('estado_nuevo'), self::ESTADOS_FORMALIZADOS, true);
    }

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
