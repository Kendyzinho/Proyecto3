<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Un estudiante habilitado (o bloqueado por prerrequisito) para un tipo de práctica en un período
class Habilitacion extends Model
{
    protected $table = 'habilitaciones';

    protected $fillable = [
        'carga_id', 'estudiante_id', 'tipo_practica_id', 'periodo_id',
        'cumple_prerrequisito', 'horas_requeridas', 'fecha_induccion', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cumple_prerrequisito' => 'boolean',
            'fecha_induccion' => 'date',
        ];
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function tipoPractica(): BelongsTo
    {
        return $this->belongsTo(TipoPractica::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(CargaNomina::class, 'carga_id');
    }
}
