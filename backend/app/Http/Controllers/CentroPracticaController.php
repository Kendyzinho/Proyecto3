<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\CentroPractica;
use App\Models\Periodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CentroPracticaController extends Controller
{
    private const ESTADOS_QUE_RESERVAN_CUPO = ['confirmada', 'en_curso'];
    private const ESTADOS_VISIBLES_EN_HISTORIAL = ['confirmada', 'en_curso', 'finalizada', 'cancelada'];

    public function index(Request $request): JsonResponse
    {
        $esCoordinadora = $request->user()?->rol === 'coordinadora';
        $query = $this->catalogQuery($esCoordinadora);

        if (!$esCoordinadora) {
            $this->restringirACentrosSeleccionables($query);
        }

        if ($request->filled('buscar')) {
            $term = trim($request->string('buscar')->toString());
            $query->where(fn (Builder $builder) => $builder
                ->where('nombre', 'like', "%{$term}%")
                ->orWhere('razon_social', 'like', "%{$term}%")
                ->orWhere('rut', 'like', "%{$term}%")
                ->orWhereHas('supervisores', fn (Builder $supervisores) => $supervisores->where('nombre', 'like', "%{$term}%")));
        }

        if ($request->query('estado') === 'activo') {
            $query->whereNotNull('fecha_alta')->where(fn (Builder $builder) => $builder
                ->whereNull('fecha_baja')->orWhereDate('fecha_baja', '>', today()));
        } elseif ($request->query('estado') === 'propuesto') {
            $query->whereNull('fecha_baja')->whereNull('fecha_alta');
        } elseif ($request->query('estado') === 'baja') {
            $query->whereNotNull('fecha_baja')->whereDate('fecha_baja', '<=', today());
        }

        return response()->json(['data' => $query->orderBy('razon_social')->get()->map(fn (CentroPractica $centro) => $this->serializeCentro($centro, $esCoordinadora))]);
    }

    public function show(Request $request, CentroPractica $centroPractica): JsonResponse
    {
        $esCoordinadora = $request->user()?->rol === 'coordinadora';
        $query = $this->catalogQuery($esCoordinadora)->whereKey($centroPractica->id);
        if (!$esCoordinadora) {
            $this->restringirACentrosSeleccionables($query);
        }

        return response()->json(['data' => $this->serializeCentro($query->firstOrFail(), $esCoordinadora)]);
    }

    public function areas(): JsonResponse
    {
        return response()->json(['data' => Area::query()->whereNull('fecha_baja')->orderBy('nombre')->get(['id', 'nombre'])]);
    }

    public function storeArea(Request $request): JsonResponse
    {
        $this->requireCoordinator($request);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('areas', 'nombre')],
        ]);
        $area = Area::create(['nombre' => trim($data['nombre'])]);

        return response()->json(['data' => $area->only(['id', 'nombre'])], 201);
    }

    public function periodos(): JsonResponse
    {
        return response()->json(['data' => Periodo::query()->orderByDesc('anio')->orderByDesc('semestre')->get(['id', 'anio', 'semestre'])]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireCoordinator($request);
        $data = $this->validateCentro($request);

        $centro = DB::transaction(function () use ($data) {
            $this->assertUniqueInternalCenter($data['es_interno']);
            $centro = CentroPractica::create($this->centerAttributes($data));
            $this->syncRelations($centro, $data);

            return $centro;
        });

        return response()->json(['data' => $this->serializeCentro($this->catalogQuery()->findOrFail($centro->id), true)], 201);
    }

    public function update(Request $request, CentroPractica $centroPractica): JsonResponse
    {
        $this->requireCoordinator($request);
        $data = $this->validateCentro($request, $centroPractica);

        DB::transaction(function () use ($centroPractica, $data) {
            $this->assertUniqueInternalCenter($data['es_interno'], $centroPractica->id);
            $centroPractica->update($this->centerAttributes($data));
            $this->syncRelations($centroPractica, $data);
        });

        return response()->json(['data' => $this->serializeCentro($this->catalogQuery()->findOrFail($centroPractica->id), true)]);
    }

    public function darDeBaja(Request $request, CentroPractica $centroPractica): JsonResponse
    {
        $this->requireCoordinator($request);
        $data = $request->validate([
            'motivo_baja' => ['required', 'string', 'max:1000'],
            'fecha_baja' => ['nullable', 'date'],
        ]);

        if ($this->hasCommittedPractices($centroPractica)) {
            throw ValidationException::withMessages([
                'centro' => 'No se puede dar de baja mientras tenga una práctica asignada, aprobada o en curso.',
            ]);
        }

        $centroPractica->update([
            'fecha_baja' => $data['fecha_baja'] ?? now()->toDateString(),
            'motivo_baja' => trim($data['motivo_baja']),
        ]);

        return response()->json(['data' => $this->serializeCentro($this->catalogQuery()->findOrFail($centroPractica->id), true)]);
    }

    public function reactivar(Request $request, CentroPractica $centroPractica): JsonResponse
    {
        $this->requireCoordinator($request);

        $centroPractica->update(['fecha_baja' => null, 'motivo_baja' => null]);

        return response()->json(['data' => $this->serializeCentro($this->catalogQuery()->findOrFail($centroPractica->id), true)]);
    }

    private function catalogQuery(bool $includeStudentHistory = true): Builder
    {
        $relations = [
            'areas:id,nombre',
            'supervisores',
            'acuerdos' => fn ($query) => $query->orderByDesc('fecha_inicio'),
            'requerimientos.periodo',
            'requerimientos.areas:id,nombre',
        ];
        if ($includeStudentHistory) {
            $relations += [
                'supervisores.practicas.habilitacion.estudiante.user:id,name,email',
                'supervisores.practicas.habilitacion.periodo',
                'supervisores.practicas.historiales',
                'supervisores.practicas.dictamenes',
            ];
        }

        return CentroPractica::query()->with($relations);
    }

    private function restringirACentrosSeleccionables(Builder $query): void
    {
        $query->whereNotNull('fecha_alta')
            ->where(fn (Builder $builder) => $builder->whereNull('fecha_baja')->orWhereDate('fecha_baja', '>', today()))
            ->where(function (Builder $builder) {
                $builder->where('es_interno', true)->orWhereHas('acuerdos', fn (Builder $acuerdos) => $acuerdos
                    ->whereNotNull('fecha_inicio')
                    ->whereDate('fecha_inicio', '<=', today())
                    ->where(fn (Builder $vigencia) => $vigencia->whereNull('fecha_termino')->orWhereDate('fecha_termino', '>=', today())));
            });
    }

    private function serializeCentro(CentroPractica $centro, bool $includeStudentHistory = false): array
    {
        $data = $centro->toArray();
        $data['estado'] = $this->estadoCentro($centro);
        $data['acuerdos'] = $centro->acuerdos->map(function ($acuerdo) use ($centro, $includeStudentHistory) {
            $agreementStatus = $centro->es_interno ? 'exento' : match (true) {
                $acuerdo->fecha_inicio === null => 'en_tramite',
                $acuerdo->fecha_inicio->isFuture() => 'en_tramite',
                $acuerdo->fecha_termino !== null && $acuerdo->fecha_termino->lessThan(today()) => 'vencido',
                default => 'vigente',
            };

            $fields = $includeStudentHistory
                ? $acuerdo->toArray()
                : $acuerdo->only(['id', 'tipo', 'fecha_inicio', 'fecha_termino']);

            return [...$fields, 'estado' => $agreementStatus];
        })->values()->all();
        $data['estado_acuerdo'] = $centro->es_interno
            ? 'exento'
            : ($data['acuerdos'][0]['estado'] ?? 'sin_acuerdo');
        $data['requerimientos'] = $centro->requerimientos->map(function ($requerimiento) use ($centro) {
            $ocupados = DB::table('practicas')
                ->join('supervisores', 'supervisores.id', '=', 'practicas.supervisor_id')
                ->join('habilitaciones', 'habilitaciones.id', '=', 'practicas.habilitacion_id')
                ->join('tipos_practica', 'tipos_practica.id', '=', 'habilitaciones.tipo_practica_id')
                ->where('supervisores.centro_id', $centro->id)
                ->where('habilitaciones.periodo_id', $requerimiento->periodo_id)
                ->where('tipos_practica.categoria', $requerimiento->categoria)
                ->whereRaw('(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = practicas.id order by hp.fecha_hora desc, hp.id desc limit 1) in (?, ?)', self::ESTADOS_QUE_RESERVAN_CUPO)
                ->count();

            return [...$requerimiento->toArray(), 'ocupados' => $ocupados, 'disponibles' => max(0, $requerimiento->cupos - $ocupados)];
        })->values()->all();
        $data['estudiantes'] = $includeStudentHistory ? $centro->supervisores->flatMap(function ($supervisor) {
            return $supervisor->practicas->map(function ($practica) use ($supervisor) {
                $habilitacion = $practica->habilitacion;
                $estudiante = $habilitacion?->estudiante;
                $periodo = $habilitacion?->periodo;
                $estado = $practica->historiales->first()?->estado_nuevo;
                $dictamen = $practica->dictamenes->sortByDesc('fecha')->first();

                return [
                    'practica_id' => $practica->id,
                    'nombre' => $estudiante?->user?->name,
                    'correo' => $estudiante?->user?->email,
                    'periodo' => $periodo ? "{$periodo->semestre}° sem {$periodo->anio}" : null,
                    'tipo_practica_id' => $habilitacion?->tipo_practica_id,
                    'supervisor' => $supervisor->nombre,
                    'fecha_inicio' => $practica->fecha_inicio?->toDateString(),
                    'fecha_fin' => $practica->fecha_fin?->toDateString(),
                    'estado' => $estado,
                    'resultado' => $dictamen?->resultado,
                ];
            })->filter(fn (?array $estudiante) => in_array($estudiante['estado'] ?? null, self::ESTADOS_VISIBLES_EN_HISTORIAL, true));
        })->values()->all() : [];

        return $data;
    }

    private function estadoCentro(CentroPractica $centro): string
    {
        if ($centro->fecha_baja !== null && $centro->fecha_baja->lessThanOrEqualTo(today())) {
            return 'dado_de_baja';
        }
        if ($centro->fecha_alta === null) {
            return 'propuesto';
        }

        return $centro->fecha_baja !== null ? 'baja_programada' : 'activo';
    }

    private function validateCentro(Request $request, ?CentroPractica $centro = null): array
    {
        if ($request->filled('rut')) {
            $request->merge(['rut' => $this->formatRut((string) $request->input('rut'))]);
        }

        $data = $request->validate([
            'rut' => [
                'required', 'string', 'max:12',
                Rule::unique('centros_practica', 'rut')->ignore($centro?->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && !$this->validRut((string) $value)) {
                        $fail('Ingresa un RUT chileno válido.');
                    }
                },
            ],
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'es_interno' => ['required', 'boolean'],
            'fecha_alta' => ['nullable', 'date'],
            'area_ids' => ['sometimes', 'array'],
            'area_ids.*' => ['integer', 'distinct', 'exists:areas,id'],
            'requerimientos' => ['sometimes', 'array'],
            'requerimientos.*.periodo_id' => ['required', 'integer', 'exists:periodos,id'],
            'requerimientos.*.categoria' => ['required', Rule::in(['laboral', 'profesional'])],
            'requerimientos.*.cupos' => ['required', 'integer', 'min:0', 'max:65535'],
            'requerimientos.*.area_ids' => ['sometimes', 'array'],
            'requerimientos.*.area_ids.*' => ['integer', 'distinct', 'exists:areas,id'],
        ]);

        foreach ($data['requerimientos'] ?? [] as $index => $row) {
            foreach (array_slice($data['requerimientos'] ?? [], $index + 1) as $other) {
                if ((int) $row['periodo_id'] === (int) $other['periodo_id'] && $row['categoria'] === $other['categoria']) {
                    throw ValidationException::withMessages([
                        'requerimientos' => 'No repitas la categoría para el mismo período; los cupos laborales se comparten entre Laboral I y II.',
                    ]);
                }
            }
        }

        if (isset($data['rut'])) {
            $data['rut'] = $this->formatRut((string) $data['rut']);
        }

        return $data;
    }

    private function centerAttributes(array $data): array
    {
        return [
            'rut' => $data['rut'] ?? null,
            'razon_social' => trim($data['razon_social']),
            'nombre' => trim($data['nombre']),
            'direccion' => isset($data['direccion']) ? trim($data['direccion']) : null,
            'telefono' => isset($data['telefono']) ? trim($data['telefono']) : null,
            'es_interno' => $data['es_interno'],
            'fecha_alta' => $data['fecha_alta'] ?? null,
        ];
    }

    private function syncRelations(CentroPractica $centro, array $data): void
    {
        if (array_key_exists('area_ids', $data)) {
            $centro->areas()->sync($data['area_ids']);
        }

        if (array_key_exists('requerimientos', $data)) {
            $centro->requerimientos()->delete();
            foreach ($data['requerimientos'] as $row) {
                $requerimiento = $centro->requerimientos()->create([
                    'periodo_id' => $row['periodo_id'],
                    'categoria' => $row['categoria'],
                    'cupos' => $row['cupos'],
                ]);
                if (isset($row['area_ids'])) {
                    $requerimiento->areas()->sync($row['area_ids']);
                }
            }
        }
    }

    private function assertUniqueInternalCenter(bool $isInternal, ?int $exceptId = null): void
    {
        if (!$isInternal) {
            return;
        }

        $exists = CentroPractica::query()->where('es_interno', true)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['es_interno' => 'La UTA se registra como un único centro interno.']);
        }
    }

    private function hasCommittedPractices(CentroPractica $centro): bool
    {
        // El estado vigente se obtiene del último HISTORIAL_PRÁCTICA.
        // La lista corresponde a estados de compromiso definidos para HU-08/HU-09.
        return DB::table('practicas')
            ->join('supervisores', 'supervisores.id', '=', 'practicas.supervisor_id')
            ->where('supervisores.centro_id', $centro->id)
            ->whereRaw('(select hp.estado_nuevo from historiales_practica hp where hp.practica_id = practicas.id order by hp.fecha_hora desc, hp.id desc limit 1) in (?, ?)', self::ESTADOS_QUE_RESERVAN_CUPO)
            ->exists();
    }

    private function requireCoordinator(Request $request): void
    {
        abort_unless($request->user()?->rol === 'coordinadora', 403, 'Esta acción es exclusiva de la Coordinadora.');
    }

    private function validRut(string $rut): bool
    {
        $value = strtoupper(preg_replace('/[^0-9K]/', '', $rut) ?? '');
        if (!preg_match('/^\d{7,8}[0-9K]$/', $value)) {
            return false;
        }

        $body = substr($value, 0, -1);
        $digit = $this->rutCheckDigit($body);
        $expected = $digit === 'K' ? 'K' : (string) $digit;

        return substr($value, -1) === $expected;
    }

    private function rutCheckDigit(string $body): int|string
    {
        $sum = 0;
        $factor = 2;
        for ($index = strlen($body) - 1; $index >= 0; $index--) {
            $sum += ((int) $body[$index]) * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $digit = 11 - ($sum % 11);

        return match ($digit) { 11 => 0, 10 => 'K', default => $digit };
    }

    private function formatRut(string $rut): string
    {
        $value = strtoupper(preg_replace('/[^0-9K]/', '', $rut) ?? '');

        return substr($value, 0, -1).'-'.substr($value, -1);
    }
}
