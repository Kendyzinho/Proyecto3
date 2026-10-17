<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// La práctica cuelga de la habilitación (estudiante + tipo + período); el centro sale del supervisor
class Practica extends Model
{
    protected $fillable = ['habilitacion_id', 'supervisor_id', 'origen', 'modalidad', 'tareas', 'fecha_inicio', 'fecha_fin'];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
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

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }
}
