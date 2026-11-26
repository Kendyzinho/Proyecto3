<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Lo que se contestó en un ítem: una opción o un texto
class DetalleRespuesta extends Model
{
    protected $table = 'detalles_respuesta';

    protected $fillable = ['respuesta_id', 'item_id', 'opcion_id', 'texto'];
}
