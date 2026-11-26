<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Practica extends Model
{
    // Estados de una práctica ya aprobada: ocupan cupo y pueden tener documentos formales
    public const ESTADOS_FORMALIZADOS = ['confirmada', 'en_curso', 'finalizada'];

    protected $table = 'practicas';

    protected $fillable = [
        'habilitacion_id', 'supervisor_id', 'origen', 'centro_propuesto_en_solicitud',
        'supervisor_propuesto_en_solicitud', 'modalidad', 'tareas', 'fecha_inicio', 'fecha_fin',
        'justificacion_excepcional', 'registrada_por',
    ];

    // El estado de una práctica es la última fila de su historial
    public function scopeConEstado(Builder $consulta, array $estados): Builder
    {
        $marcas = implode(', ', array_fill(0, count($estados), '?'));

        return $consulta->whereRaw(
            "(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = practicas.id order by hp.fecha_hora desc, hp.id desc limit 1) in ({$marcas})",
            $estados,
        );
    }

    public function scopeFormalizadas(Builder $consulta): Builder
    {
        return $consulta->conEstado(self::ESTADOS_FORMALIZADOS);
    }

    public function estaFormalizada(): bool
    {
        return in_array($this->historiales()->value('estado_nuevo'), self::ESTADOS_FORMALIZADOS, true);
    }

    public function autoraRegistro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por');
    }

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'centro_propuesto_en_solicitud' => 'boolean',
            'supervisor_propuesto_en_solicitud' => 'boolean',
        ];
    }

    public function habilitacion(): BelongsTo
    {
        return $this->belongsTo(Habilitacion::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(HistorialPractica::class)->orderByDesc('fecha_hora')->orderByDesc('id');
    }

    public function dictamenes(): HasMany
    {
        return $this->hasMany(Dictamen::class)->orderByDesc('fecha')->orderByDesc('id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }

    // El proceso de cierre futuro debe consultar este requisito antes de registrar «finalizada».
    public function cumpleRequisitoPercepcionParaFinalizar(): bool
    {
        return DB::table('respuestas_instrumento as respuesta')
            ->join('plantillas_instrumento as plantilla', 'plantilla.id', '=', 'respuesta.plantilla_id')
            ->where('respuesta.practica_id', $this->id)
            ->where('plantilla.instrumento', 'encuesta')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('items')
                ->whereColumn('items.plantilla_id', 'plantilla.id')->where('items.clave', 'clima_laboral'))
            ->whereNotNull('respuesta.fecha_envio')
            ->exists();
    }

    // HU-15: visitas técnicas que revisan esta práctica
    public function visitas(): BelongsToMany
    {
        return $this->belongsToMany(VisitaTecnica::class, 'visita_practica', 'practica_id', 'visita_id')->withTimestamps();
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class, 'practica_id');
    }

    public function registrosDiarios(): HasMany
    {
        return $this->hasMany(RegistroDiario::class, 'practica_id')->orderByDesc('fecha')->orderByDesc('id');
    }

    // Cada cambio registra el horario completo: el vigente es la versión más reciente
    public function horarioVigente(): Collection
    {
        $desde = $this->horarios->max(fn (Horario $horario) => $horario->vigente_desde->toDateString());

        return $this->horarios
            ->filter(fn (Horario $horario) => $horario->vigente_desde->toDateString() === $desde)
            ->sortBy('dia_semana')->values();
    }
}
