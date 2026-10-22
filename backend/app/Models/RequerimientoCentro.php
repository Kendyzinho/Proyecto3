<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RequerimientoCentro extends Model
{
    protected $table = 'requerimientos_centro';

    protected $fillable = ['centro_id', 'periodo_id', 'categoria', 'cupos'];

    public function centro(): BelongsTo
    {
        return $this->belongsTo(CentroPractica::class, 'centro_id');
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'requerimiento_area', 'requerimiento_id', 'area_id');
    }
}
