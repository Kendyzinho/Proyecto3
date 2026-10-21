<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Area extends Model
{
    protected $fillable = ['nombre', 'fecha_baja'];

    protected function casts(): array
    {
        return ['fecha_baja' => 'date'];
    }

    public function centros(): BelongsToMany
    {
        return $this->belongsToMany(CentroPractica::class, 'centro_area', 'area_id', 'centro_id')->withTimestamps();
    }
}
