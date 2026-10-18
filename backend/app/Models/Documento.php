<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Documento extends Model
{
    protected $fillable = ['practica_id', 'user_id', 'tipo', 'archivo_o_url', 'fecha', 'fecha_envio'];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
            'fecha_envio' => 'datetime',
        ];
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
