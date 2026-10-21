<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroPractica extends Model
{
    protected $table = 'centros_practica';

    protected $fillable = [
        'rut', 'razon_social', 'nombre', 'direccion', 'telefono', 'es_interno',
        'fecha_alta', 'fecha_baja', 'motivo_baja',
    ];

    protected function casts(): array
    {
        return ['es_interno' => 'boolean', 'fecha_alta' => 'date', 'fecha_baja' => 'date'];
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'centro_area', 'centro_id', 'area_id')->withTimestamps();
    }

    public function supervisores(): HasMany
    {
        return $this->hasMany(Supervisor::class, 'centro_id');
    }

    public function practicas(): HasMany
    {
        return $this->hasManyThrough(Practica::class, Supervisor::class, 'centro_id', 'supervisor_id');
    }

    public function acuerdos(): HasMany
    {
        return $this->hasMany(AcuerdoColaboracion::class, 'centro_id');
    }

    // Último acuerdo registrado, esté vigente, en trámite o vencido
    public function acuerdoActual(): ?AcuerdoColaboracion
    {
        return $this->acuerdos()->latest('id')->first();
    }

    // Art. 15: sin acuerdo vigente el centro no recibe estudiantes. El centro interno (UTA) no lo necesita
    public function puedeRecibirEstudiantes(): bool
    {
        return $this->es_interno || $this->acuerdoActual()?->estado() === 'vigente';
    }

    public function requerimientos(): HasMany
    {
        return $this->hasMany(RequerimientoCentro::class, 'centro_id');
    }
}
