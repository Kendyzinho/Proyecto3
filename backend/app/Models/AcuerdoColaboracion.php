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

    // fecha_inicio vacía = en trámite (ER)
    // en_tramite: falta el PDF firmado o la fecha de inicio. programado: firmado, pero rige desde una fecha futura
    public function estado(): string
    {
        if (!$this->archivo_pdf || $this->fecha_inicio === null) {
            return 'en_tramite';
        }
        if ($this->fecha_inicio->isFuture()) {
            return 'programado';
        }

        return $this->fecha_termino !== null && $this->fecha_termino->isPast() && ! $this->fecha_termino->isToday()
            ? 'vencido'
            : 'vigente';
    }

    // Lo que todavía se puede corregir: un acuerdo firmado y en vigor (o ya vencido) no se modifica, se renueva
    public function esEditable(): bool
    {
        return in_array($this->estado(), ['en_tramite', 'programado'], true);
    }
}
