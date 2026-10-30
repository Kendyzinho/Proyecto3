<?php

namespace Database\Seeders;

use App\Models\AcuerdoColaboracion;
use App\Models\CentroPractica;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\Supervisor;
use App\Models\TipoPractica;
use App\Models\User;
use Illuminate\Database\Seeder;

// Centros, supervisores, acuerdos y prácticas de ejemplo del 2º semestre 2026 (pantalla 08).
// Sirven para probar la HU-10 mientras no existan las HU-08, 09 y 11, que crean las prácticas.
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $periodo = Periodo::where('anio', 2026)->where('semestre', 2)->firstOrFail();
        $tipo = fn (string $nombre) => TipoPractica::where('nombre', $nombre)->whereHas('malla', fn ($q) => $q->whereNotNull('vigente_desde'))->firstOrFail();

        $municipalidad = $this->centro('69.010.100-9', 'Ilustre Municipalidad de Arica', 'Municipalidad de Arica', 'Av. Rafael Sotomayor 415, Arica', '2023-03-01', '2028-03-31');
        $productora = $this->centro('76.543.210-3', 'Productora Norte Visual SpA', 'Productora Norte Visual', 'Av. Santa María 2145, Arica', '2025-01-01', '2027-12-31');
        $fundacion = $this->centro('65.123.456-5', 'Fundación Universidad de Tarapacá', 'Fundación UTA', '18 de Septiembre 2222, Arica', '2024-06-01', '2027-06-30');
        $corfo = $this->centro('60.706.000-2', 'Corporación de Fomento de la Producción', 'Corfo Arica y Parinacota', 'Av. Diego Portales 840, Arica', null, null);

        $paula = $this->supervisor($municipalidad, 'Paula Contreras', 'pcontreras@muniarica.cl', 'Encargada de Diseño', 'Dirección de Comunicaciones');
        $sergio = $this->supervisor($productora, 'Sergio Paredes', 'sparedes@nortevisual.cl', 'Director de Arte', 'Producción');
        $carolina = $this->supervisor($fundacion, 'Carolina Mamani Ticona', 'cmamani@fundacionuta.cl', 'Jefa de Comunicaciones', 'Comunicaciones');
        $rodrigo = $this->supervisor($corfo, 'Rodrigo Álvarez Soto', 'ralvarez@corfo.cl', 'Ejecutivo de Fomento', 'Dirección Regional');

        $casos = [
            ['21.334.120-0', 'Camila Rojas Pizarro', 'camila.rojas', 2025, 'Laboral I', $paula, '2026-08-17', '2026-09-04'],
            ['20.456.789-1', 'Matías Herrera Lillo', 'estudiante', 2022, 'Profesional', $sergio, '2026-08-17', '2026-11-27'],
            ['21.087.552-2', 'Josefa Ramírez Tapia', 'josefa.ramirez', 2022, 'Profesional', $carolina, '2026-09-07', '2026-11-27'],
            ['21.502.611-6', 'Antonia Vega Muñoz', 'antonia.vega', 2024, 'Laboral II', $sergio, '2026-09-14', '2026-10-02'],
            ['21.245.678-0', 'Diego Choque Vargas', 'diego.choque', 2022, 'Profesional', $rodrigo, '2026-09-21', '2026-12-11'],
        ];

        foreach ($casos as [$rut, $nombre, $correo, $anio, $practica, $supervisor, $inicio, $fin]) {
            $usuario = User::firstOrCreate(['rut' => $rut], [
                'name' => $nombre, 'email' => "{$correo}@alumnos.uta.cl", 'password' => 'password',
            ]);
            $estudiante = Estudiante::firstOrCreate(['user_id' => $usuario->id], ['anio_ingreso' => $anio]);
            $tipoPractica = $tipo($practica);
            $habilitacion = Habilitacion::firstOrCreate(
                ['estudiante_id' => $estudiante->id, 'tipo_practica_id' => $tipoPractica->id, 'periodo_id' => $periodo->id],
                ['cumple_prerrequisito' => true, 'horas_requeridas' => $tipoPractica->horas_requeridas],
            );
            Practica::create([
                'habilitacion_id' => $habilitacion->id,
                'supervisor_id' => $supervisor->id,
                'origen' => $practica === 'Profesional' ? 'solicitud' : 'asignacion',
                'modalidad' => 'presencial',
                'fecha_inicio' => $inicio,
                'fecha_fin' => $fin,
            ]);
        }
    }

    // Centro con acuerdo; sin fecha de inicio el acuerdo queda en trámite.
    // Reutiliza el centro y su acuerdo si CentrosDemoSeeder ya los creó.
    private function centro(string $rut, string $razonSocial, string $nombre, string $direccion, ?string $inicio, ?string $termino): CentroPractica
    {
        $centro = CentroPractica::firstOrCreate(['rut' => $rut], [
            'razon_social' => $razonSocial, 'nombre' => $nombre, 'direccion' => $direccion, 'fecha_alta' => '2023-01-01',
        ]);
        AcuerdoColaboracion::firstOrCreate(['centro_id' => $centro->id, 'tipo' => 'tradicional'], [
            'representante_legal' => 'Representante de '.$nombre,
            'fecha_inicio' => $inicio, 'fecha_termino' => $termino, 'archivo_pdf' => $inicio ? PdfAcuerdoDemo::ruta() : null,
        ]);

        return $centro;
    }

    private function supervisor(CentroPractica $centro, string $nombre, string $correo, string $cargo, string $unidad): Supervisor
    {
        return Supervisor::firstOrCreate(['centro_id' => $centro->id, 'nombre' => $nombre], [
            'correo' => $correo, 'cargo' => $cargo, 'unidad' => $unidad, 'fecha_alta' => '2023-01-01',
        ]);
    }
}
