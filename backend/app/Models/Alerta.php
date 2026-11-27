<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// HU-14: luz roja, naranja o amarilla sobre una práctica.
// Se cierra sola al desaparecer su causa (fecha_resolucion) o cuando la Coordinadora la atiende (fecha_atencion).
class Alerta extends Model
{
    protected $table = 'alertas';

    protected $fillable = [
        'practica_id', 'coordinadora_id', 'tipo', 'fecha_referencia', 'fecha_generada',
        'fecha_atencion', 'fecha_resolucion', 'nota_atencion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_referencia' => 'date',
            'fecha_generada' => 'datetime',
            'fecha_atencion' => 'datetime',
            'fecha_resolucion' => 'datetime',
        ];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    // Quién la marcó como atendida
    public function coordinadora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinadora_id');
    }

    // Lo que todavía le toca revisar a la Coordinadora
    public function scopePendientes(Builder $query): void
    {
        $query->whereNull('fecha_atencion')->whereNull('fecha_resolucion');
    }

    // Lo que el estudiante o el supervisor todavía tienen que poner al día
    public function scopeSinResolver(Builder $query): void
    {
        $query->whereNull('fecha_resolucion');
    }

    public function estaCerrada(): bool
    {
        return $this->fecha_atencion !== null || $this->fecha_resolucion !== null;
    }
}
