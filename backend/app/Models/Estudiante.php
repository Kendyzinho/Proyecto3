<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Estudiante extends Model
{
    protected $fillable = ['user_id', 'anio_ingreso'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Alias usado por los recursos de nómina
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Áreas de interés: la HU-08 las cruza con las de los centros
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'estudiante_area', 'estudiante_id', 'area_id')->withTimestamps();
    }

    public function habilitaciones(): HasMany
    {
        return $this->hasMany(Habilitacion::class);
    }
}
