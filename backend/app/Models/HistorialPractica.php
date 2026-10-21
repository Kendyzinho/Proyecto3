<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialPractica extends Model
{
    protected $table = 'historiales_practica';

    protected $fillable = ['practica_id', 'user_id', 'fecha_hora', 'estado_nuevo', 'motivo'];

    protected function casts(): array
    {
        return ['fecha_hora' => 'datetime'];
    }

    public function practica(): BelongsTo
    {
        return $this->belongsTo(Practica::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
