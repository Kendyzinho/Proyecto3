<?php

namespace Database\Seeders;

use App\Models\AcuerdoColaboracion;
use App\Models\Area;
use App\Models\CentroPractica;
use App\Models\Periodo;
use App\Models\RequerimientoCentro;
use Illuminate\Database\Seeder;
use App\Models\Supervisor;

/** Fichas ficticias para probar el selector y el catálogo en desarrollo. */
class CentrosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = Periodo::firstOrCreate(['anio' => 2026, 'semestre' => 2]);
        $areas = collect([
            'Diseño gráfico y diagramación', 'Fotografía', 'Diseño y desarrollo web',
            'Animación digital', 'Producción audiovisual', 'Diseño editorial',
            'Publicidad y marketing digital',
        ])->mapWithKeys(fn (string $nombre) => [$nombre => Area::firstOrCreate(['nombre' => $nombre])->id]);

        $centros = [
            [
                'rut' => '69.010.100-9', 'razon_social' => 'Ilustre Municipalidad de Arica', 'nombre' => 'Municipalidad de Arica',
                'direccion' => 'Rafael Sotomayor 415, Arica', 'telefono' => '+56 58 220 6000', 'es_interno' => false,
                'representante' => 'Representante de prueba', 'supervisor' => ['nombre' => 'Paula Contreras', 'correo' => 'pcontreras@example.test', 'cargo' => 'Diseñadora', 'unidad' => 'Comunicaciones'],
                'areas' => ['Diseño gráfico y diagramación', 'Fotografía'], 'cupos' => ['laboral' => 4, 'profesional' => 2],
            ],
            [
                'rut' => '76.543.210-8', 'razon_social' => 'Productora Norte Visual SpA', 'nombre' => 'Productora Norte Visual',
                'direccion' => 'Av. Santa María 2145, Arica', 'telefono' => '+56 58 225 3210', 'es_interno' => false,
                'representante' => 'Representante de prueba', 'supervisor' => ['nombre' => 'Sergio Paredes', 'correo' => 'sparedes@example.test', 'cargo' => 'Director de Arte', 'unidad' => 'Producción'],
                'areas' => ['Producción audiovisual', 'Animación digital', 'Diseño gráfico y diagramación'], 'cupos' => ['laboral' => 2, 'profesional' => 1],
            ],
            [
                'rut' => '70.770.800-5', 'razon_social' => 'Universidad de Tarapacá', 'nombre' => 'Universidad de Tarapacá',
                'direccion' => 'Av. 18 de Septiembre 2222, Arica', 'telefono' => '+56 58 220 5000', 'es_interno' => true,
                'representante' => null, 'supervisor' => ['nombre' => 'Daniela Rojas', 'correo' => 'daniela.rojas@example.test', 'cargo' => 'Diseñadora', 'unidad' => 'Escuela de Diseño'],
                'areas' => ['Diseño y desarrollo web', 'Diseño gráfico y diagramación', 'Producción audiovisual'], 'cupos' => ['laboral' => 4, 'profesional' => 2],
            ],
        ];

        foreach ($centros as $datos) {
            $centro = CentroPractica::firstOrCreate(
                ['rut' => $datos['rut']],
                [
                    'razon_social' => $datos['razon_social'], 'nombre' => $datos['nombre'],
                    'direccion' => $datos['direccion'], 'telefono' => $datos['telefono'],
                    'es_interno' => $datos['es_interno'], 'fecha_alta' => today(),
                ],
            );

            if (!$datos['es_interno']) {
                AcuerdoColaboracion::firstOrCreate(
                    ['centro_id' => $centro->id, 'tipo' => 'tradicional'],
                    ['representante_legal' => $datos['representante'], 'fecha_inicio' => '2025-01-01', 'fecha_termino' => '2027-12-31', 'archivo_pdf' => PdfAcuerdoDemo::ruta()],
                );
            }

            Supervisor::firstOrCreate(
                ['centro_id' => $centro->id, 'correo' => $datos['supervisor']['correo']],
                [
                    'nombre' => $datos['supervisor']['nombre'], 'cargo' => $datos['supervisor']['cargo'],
                    'unidad' => $datos['supervisor']['unidad'], 'fecha_alta' => today(),
                ],
            );

            $centro->areas()->syncWithoutDetaching(collect($datos['areas'])->map(fn (string $nombre) => $areas[$nombre])->all());

            foreach ($datos['cupos'] as $categoria => $cupos) {
                $requerimiento = RequerimientoCentro::firstOrCreate(
                    ['centro_id' => $centro->id, 'periodo_id' => $periodo->id, 'categoria' => $categoria],
                    ['cupos' => $cupos],
                );
                $requerimiento->areas()->syncWithoutDetaching(collect($datos['areas'])->map(fn (string $nombre) => $areas[$nombre])->all());
            }
        }
    }
}
