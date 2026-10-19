<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $fillable = ['user_id', 'anio_ingreso'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function habilitaciones(): HasMany
    {
        return $this->hasMany(Habilitacion::class);
    }
}
