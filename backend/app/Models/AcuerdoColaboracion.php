<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcuerdoColaboracion extends Model
{
    protected $table = 'acuerdos_colaboracion';

    protected $fillable = ['centro_id', 'tipo', 'representante_legal', 'fecha_inicio', 'fecha_termino', 'archivo_pdf'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_termino' => 'date'];
    }

    public function centro(): BelongsTo
    {
        return $this->belongsTo(CentroPractica::class, 'centro_id');
    }
}
