<?php

namespace App\Services;

use App\Models\Periodo;
use App\Models\Practica;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListadoPracticasService
{
    public const ESTADOS = [
        ['valor' => 'en_curso', 'texto' => 'En curso'],
        ['valor' => 'posible_abandono', 'texto' => 'Posible abandono'],
        ['valor' => 'por_iniciar', 'texto' => 'Por iniciar'],
    ];

    public const ENCABEZADOS = ['RUT', 'Estudiante', 'Correo', 'Práctica', 'Centro', 'Supervisor(a)', 'Estado', 'Fecha inicio', 'Fecha término'];

    public function catalogo(Periodo $periodo): array
    {
        $tipos = $this->filas($periodo, [])
            ->map(fn (array $fila) => ['id' => $fila['tipo_practica_id'], 'nombre' => $fila['practica']])
            ->unique('id')
            ->sortBy('nombre')
            ->values()
            ->all();

        return ['tipos' => $tipos, 'estados' => self::ESTADOS];
    }

    public function listar(Periodo $periodo, array $filtros = []): Collection
    {
        return $this->filas($periodo, $filtros);
    }

    public function exportar(Periodo $periodo, array $filtros, LectorPlanilla $lector): string
    {
        $filas = $this->listar($periodo, $filtros)
            ->map(fn (array $fila) => [
                $fila['rut'], $fila['estudiante'], $fila['correo'], $fila['practica'], $fila['centro'],
                $fila['supervisor'], $fila['estado_texto'], $fila['fecha_inicio'], $fila['fecha_fin'],
            ])
            ->all();

        return $lector->crearPlanillaConEncabezados(self::ENCABEZADOS, $filas);
    }

    private function filas(Periodo $periodo, array $filtros): Collection
    {
        $q = Str::of((string) ($filtros['q'] ?? ''))->ascii()->lower()->squish()->toString();
        $tipoId = isset($filtros['tipo_practica_id']) ? (int) $filtros['tipo_practica_id'] : null;
        $estado = $filtros['estado'] ?? null;

        return Practica::with([
            'habilitacion.estudiante.usuario',
            'habilitacion.tipoPractica',
            'supervisor.centro',
            'historiales',
            'alertas' => fn ($consulta) => $consulta->where('tipo', 'abandono')->pendientes(),
        ])
            ->whereHas('habilitacion', fn ($consulta) => $consulta->where('periodo_id', $periodo->id))
            ->get()
            ->map(fn (Practica $practica) => $this->serializar($practica))
            ->filter(fn (array $fila) => $this->cumple($fila, $q, $tipoId, $estado))
            ->sortBy(fn (array $fila) => [$fila['estudiante'], $fila['practica']])
            ->values();
    }

    private function serializar(Practica $practica): array
    {
        $usuario = $practica->habilitacion->estudiante->usuario;
        $historico = $practica->historiales->first()?->estado_nuevo ?? 'borrador';
        $estado = match (true) {
            $practica->alertas->isNotEmpty() => 'posible_abandono',
            $historico === 'en_curso' => 'en_curso',
            $historico === 'confirmada' => 'por_iniciar',
            default => $historico,
        };

        return [
            'id' => $practica->id,
            'rut' => $usuario->rut,
            'estudiante' => $usuario->name,
            'correo' => $usuario->email,
            'tipo_practica_id' => $practica->habilitacion->tipo_practica_id,
            'practica' => $practica->habilitacion->tipoPractica->nombre,
            'centro' => $practica->supervisor?->centro?->nombre ?? '',
            'supervisor' => $practica->supervisor?->nombre ?? '',
            'estado' => $estado,
            'estado_texto' => $this->textoEstado($estado),
            'fecha_inicio' => $practica->fecha_inicio?->toDateString(),
            'fecha_fin' => $practica->fecha_fin?->toDateString(),
        ];
    }

    private function textoEstado(string $estado): string
    {
        return match ($estado) {
            'en_curso' => 'En curso',
            'posible_abandono' => 'Posible abandono',
            'por_iniciar' => 'Por iniciar',
            'por_revisar' => 'Por revisar',
            'devuelta' => 'Devuelta',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => 'Borrador',
        };
    }

    private function cumple(array $fila, string $q, ?int $tipoId, ?string $estado): bool
    {
        if ($tipoId !== null && $fila['tipo_practica_id'] !== $tipoId) {
            return false;
        }
        if ($estado !== null && $fila['estado'] !== $estado) {
            return false;
        }
        if ($q === '') {
            return true;
        }

        foreach ([$fila['rut'], $fila['estudiante'], $fila['correo'], $fila['practica'], $fila['centro'], $fila['supervisor'], $fila['estado_texto']] as $valor) {
            if (Str::of((string) $valor)->ascii()->lower()->contains($q)) {
                return true;
            }
        }

        return false;
    }
}
