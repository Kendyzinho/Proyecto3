<?php

namespace App\Services;

use App\Models\CargaNomina;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\Malla;
use App\Models\Periodo;
use App\Models\TipoPractica;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// HU-07: la Secretaria carga la nómina del período y el sistema revisa cada fila.
// El prerrequisito viene en la planilla (Sí/No); quien no lo cumple queda bloqueado (Art. 8).
class NominaService
{
    public const DOMINIO_ESTUDIANTES = '@alumnos.uta.cl';

    public function __construct(private readonly LectorPlanilla $lector)
    {
    }

    // Primer paso: revisa la planilla sin guardar nada
    public function revisar(UploadedFile $archivo, Periodo $periodo): array
    {
        $filas = $this->revisarFilas($this->lector->leer($archivo), $periodo);

        return ['resumen' => $this->resumen($filas), 'filas' => $filas];
    }

    // Segundo paso: vuelve a revisar y guarda las filas habilitadas y bloqueadas
    public function confirmar(UploadedFile $archivo, Periodo $periodo, User $secretaria): array
    {
        $filas = $this->revisarFilas($this->lector->leer($archivo), $periodo);
        $validas = array_filter($filas, fn ($f) => $f['resultado'] !== 'error');

        if ($validas === []) {
            throw ValidationException::withMessages(['archivo' => 'Ninguna fila se puede cargar. Corrige la planilla.']);
        }

        $ruta = $archivo->storeAs('nominas', now()->format('Ymd_His').'_'.Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$archivo->getClientOriginalExtension());

        $carga = DB::transaction(function () use ($validas, $filas, $periodo, $secretaria, $ruta) {
            $carga = CargaNomina::create([
                'secretaria_id' => $secretaria->id,
                'fecha_hora' => now(),
                'archivo' => $ruta,
                'filas_con_error' => count($filas) - count($validas),
            ]);

            foreach ($validas as $fila) {
                $this->habilitar($fila, $periodo, $carga->id);
            }

            return $carga;
        });

        return ['carga_id' => $carga->id] + $this->resumen($filas);
    }

    // Carga individual ("Agregar un estudiante"): sin carga asociada
    public function agregar(array $datos, Periodo $periodo): Habilitacion
    {
        $tipo = $this->tiposVigentes()->firstWhere('id', (int) $datos['tipo_practica_id']);
        $fila = $this->revisarFila([
            'rut' => (string) $datos['rut'],
            'nombre' => (string) $datos['nombre'],
            'correo' => (string) $datos['correo'],
            'anio_ingreso' => (string) $datos['anio_ingreso'],
            'practica' => $tipo?->nombre ?? '',
            'cumple' => $datos['cumple_prerrequisito'] ? 'Sí' : 'No',
        ], $periodo, $this->tiposVigentes());

        if ($fila['resultado'] === 'error') {
            // Los errores de la fila se informan con el nombre de cada campo del formulario
            $campos = ['practica' => 'tipo_practica_id', 'cumple' => 'cumple_prerrequisito'];
            throw ValidationException::withMessages(
                collect($fila['errores'])->mapWithKeys(fn ($error, $clave) => [$campos[$clave] ?? $clave => $error])->all()
            );
        }

        return DB::transaction(fn () => $this->habilitar($fila, $periodo, null));
    }

    // Nómina guardada del período
    public function listar(Periodo $periodo): Collection
    {
        return Habilitacion::with(['estudiante.usuario', 'tipoPractica'])
            ->where('periodo_id', $periodo->id)
            ->get()
            ->sortBy(fn ($h) => $h->estudiante->usuario->name)
            ->values()
            ->map(fn (Habilitacion $h) => [
                'id' => $h->id,
                'rut' => $h->estudiante->usuario->rut,
                'nombre' => $h->estudiante->usuario->name,
                'correo' => $h->estudiante->usuario->email,
                'anio_ingreso' => $h->estudiante->anio_ingreso,
                'practica' => $h->tipoPractica->nombre,
                'prerrequisito' => $h->tipoPractica->prerrequisito,
                'cumple' => $h->cumple_prerrequisito,
                'resultado' => $h->cumple_prerrequisito ? 'habilitado' : 'bloqueado',
                'origen' => $h->carga_id ? 'planilla' : 'manual',
            ]);
    }

    // Excel de la nómina guardada del período, con las columnas de la plantilla
    public function exportar(Periodo $periodo): string
    {
        $filas = $this->listar($periodo)->map(fn (array $e) => [
            $e['rut'], $e['nombre'], $e['correo'], $e['anio_ingreso'], $e['practica'], $e['cumple'] ? 'Sí' : 'No',
        ])->all();

        return $this->lector->crearPlanilla($filas);
    }

    // Prácticas de la malla vigente que se pueden habilitar
    public function tiposVigentes(): Collection
    {
        $malla = Malla::vigente();

        return $malla
            ? TipoPractica::where('malla_id', $malla->id)->whereNull('fecha_baja')->orderBy('id')->get()
            : collect();
    }

    private function revisarFilas(array $filas, Periodo $periodo): array
    {
        $tipos = $this->tiposVigentes();
        $vistos = [];

        return array_map(function (array $fila) use ($periodo, $tipos, &$vistos) {
            $revisada = $this->revisarFila($fila, $periodo, $tipos);

            // Un RUT solo puede venir una vez en la planilla
            if (! isset($revisada['errores']['rut'])) {
                if (isset($vistos[$revisada['rut']])) {
                    $revisada['errores']['rut'] = "RUT repetido en la planilla (fila {$vistos[$revisada['rut']]})";
                    $revisada['resultado'] = 'error';
                } else {
                    $vistos[$revisada['rut']] = $fila['fila'];
                }
            }

            return ['fila' => $fila['fila']] + $revisada;
        }, $filas);
    }

    private function revisarFila(array $fila, Periodo $periodo, Collection $tipos): array
    {
        $errores = [];

        $rut = Rut::normalizar($fila['rut']);
        if ($rut === null) {
            $errores['rut'] = $fila['rut'] === '' ? 'Falta el RUT' : 'RUT inválido';
        }

        $nombre = Str::squish($fila['nombre']);
        if ($nombre === '') {
            $errores['nombre'] = 'Falta el nombre';
        }

        $correo = Str::lower(trim($fila['correo']));
        if (! filter_var($correo, FILTER_VALIDATE_EMAIL) || ! Str::endsWith($correo, self::DOMINIO_ESTUDIANTES)) {
            $errores['correo'] = 'Correo no institucional';
        }

        $anio = filter_var($fila['anio_ingreso'], FILTER_VALIDATE_INT);
        if ($anio === false || $anio < 1990 || $anio > (int) now()->year) {
            $errores['anio_ingreso'] = 'Año de ingreso inválido';
        }

        $practica = Str::of($fila['practica'])->ascii()->lower()->squish()->toString();
        $tipo = $tipos->first(fn ($t) => Str::of($t->nombre)->ascii()->lower()->toString() === $practica);
        if ($tipo === null) {
            $errores['practica'] = $fila['practica'] === '' ? 'Falta la práctica' : 'La práctica no existe en la malla vigente';
        }

        $cumple = match (Str::of($fila['cumple'])->ascii()->lower()->trim()->toString()) {
            'si', 's' => true,
            'no', 'n' => false,
            default => null,
        };
        if ($cumple === null) {
            $errores['cumple'] = 'El prerrequisito debe ser Sí o No';
        }

        // Cruces con lo que ya está en la base
        if ($rut !== null) {
            $usuario = User::where('rut', $rut)->first();

            if ($usuario && $usuario->rol !== null) {
                $errores['rut'] = 'El RUT pertenece a una cuenta del personal';
            } elseif ($usuario && $tipo && $this->yaHabilitado($usuario, $tipo, $periodo)) {
                $errores['rut'] = 'Ya está en la nómina de este período';
            } elseif (! $usuario && ! isset($errores['correo']) && User::where('email', $correo)->exists()) {
                $errores['correo'] = 'El correo pertenece a otra cuenta';
            }
        }

        // Lo inválido se devuelve tal como venía, para mostrarlo y para la planilla de filas con error
        return [
            'rut' => $rut ?? $fila['rut'],
            'nombre' => $nombre,
            'correo' => $correo,
            'anio_ingreso' => $anio === false ? $fila['anio_ingreso'] : $anio,
            'tipo_practica_id' => $tipo?->id,
            'practica' => $tipo?->nombre ?? $fila['practica'],
            'prerrequisito' => $tipo?->prerrequisito,
            'cumple' => $cumple,
            'resultado' => $errores !== [] ? 'error' : ($cumple ? 'habilitado' : 'bloqueado'),
            'errores' => $errores,
        ];
    }

    private function yaHabilitado(User $usuario, TipoPractica $tipo, Periodo $periodo): bool
    {
        return Habilitacion::where('tipo_practica_id', $tipo->id)
            ->where('periodo_id', $periodo->id)
            ->whereHas('estudiante', fn ($q) => $q->where('user_id', $usuario->id))
            ->exists();
    }

    // Crea (o reutiliza) la cuenta y el estudiante, y lo habilita con las horas del tipo
    private function habilitar(array $fila, Periodo $periodo, ?int $cargaId): Habilitacion
    {
        $usuario = User::firstOrCreate(
            ['rut' => $fila['rut']],
            // Contraseña al azar: el estudiante entra con "Recuperar contraseña" (HU-02)
            ['name' => $fila['nombre'], 'email' => $fila['correo'], 'password' => Str::password(32)],
        );

        $estudiante = Estudiante::firstOrCreate(['user_id' => $usuario->id], ['anio_ingreso' => $fila['anio_ingreso']]);
        $tipo = TipoPractica::findOrFail($fila['tipo_practica_id']);

        return Habilitacion::create([
            'carga_id' => $cargaId,
            'estudiante_id' => $estudiante->id,
            'tipo_practica_id' => $tipo->id,
            'periodo_id' => $periodo->id,
            'cumple_prerrequisito' => $fila['cumple'],
            'horas_requeridas' => $tipo->horas_requeridas,
        ]);
    }

    private function resumen(array $filas): array
    {
        $conteo = array_count_values(array_column($filas, 'resultado'));

        return [
            'total' => count($filas),
            'habilitados' => $conteo['habilitado'] ?? 0,
            'bloqueados' => $conteo['bloqueado'] ?? 0,
            'con_error' => $conteo['error'] ?? 0,
        ];
    }
}
