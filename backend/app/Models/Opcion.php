<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// Alternativa de un ítem; "No aplica" no tiene valor numérico
class Opcion extends Model
{
    protected $table = 'opciones';

    protected $fillable = ['item_id', 'orden', 'etiqueta', 'valor_numerico'];
}
