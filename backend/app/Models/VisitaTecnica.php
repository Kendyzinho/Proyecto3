<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// HU-15: visita de supervisión a un centro; revisa una o más prácticas de ese centro
class VisitaTecnica extends Model
{
    protected $table = 'visitas_tecnicas';

    protected $fillable = ['coordinadora_id', 'fecha_hora', 'modalidad', 'cancelada'];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime', 'cancelada' => 'boolean'];
    }

    public function coordinadora(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordinadora_id');
    }

    public function practicas(): BelongsToMany
    {
        return $this->belongsToMany(Practica::class, 'visita_practica', 'visita_id', 'practica_id')->withTimestamps();
    }

    // Una visita agendada pasa a realizada cuando llega su fecha y hora
    public function estado(): string
    {
        if ($this->cancelada) {
            return 'cancelada';
        }

        return $this->fecha_hora->isFuture() ? 'agendada' : 'realizada';
    }
}
