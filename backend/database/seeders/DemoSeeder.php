<?php

namespace Database\Seeders;

use App\Models\AcuerdoColaboracion;
use App\Models\Area;
use App\Models\CentroPractica;
use App\Models\Estudiante;
use App\Models\Habilitacion;
use App\Models\HistorialPractica;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\RequerimientoCentro;
use App\Models\Supervisor;
use App\Models\TipoPractica;
use App\Models\User;
use App\Support\Rut;
use Illuminate\Database\Seeder;

// Datos de demostración del 2º semestre 2026: alcanzan para mostrar el flujo completo del Sprint 1.
// - Documentos (HU-10): 5 prácticas confirmadas, una en un centro con el acuerdo en trámite.
// - Solicitudes (HU-09): 2 por revisar (una con centro y supervisor propuestos) y 1 devuelta.
// - Asignación (HU-08): 2 estudiantes laborales con áreas y sin centro.
// - Solicitud nueva (HU-09): Matías, el estudiante del README, parte sin práctica.
class DemoSeeder extends Seeder
{
    private Periodo $periodo;

    private User $coordinadora;

    public function run(): void
    {
        $this->periodo = Periodo::where('anio', 2026)->where('semestre', 2)->firstOrFail();
        $this->coordinadora = User::where('rol', 'coordinadora')->orderBy('id')->firstOrFail();

        $municipalidad = $this->centro('69.010.100-9', 'Ilustre Municipalidad de Arica', 'Municipalidad de Arica', 'Av. Rafael Sotomayor 415, Arica', '2023-03-01', '2028-03-31');
        $productora = $this->centro('76.543.210-3', 'Productora Norte Visual SpA', 'Productora Norte Visual', 'Av. Santa María 2145, Arica', '2025-01-01', '2027-12-31');
        $fundacion = $this->centro('65.123.456-5', 'Fundación Universidad de Tarapacá', 'Fundación UTA', '18 de Septiembre 2222, Arica', '2024-06-01', '2027-06-30');
        $corfo = $this->centro('60.706.000-2', 'Corporación de Fomento de la Producción', 'Corfo Arica y Parinacota', 'Av. Diego Portales 840, Arica', null, null);

        $this->oferta($municipalidad, ['Diseño gráfico y diagramación', 'Fotografía'], ['laboral' => 4, 'profesional' => 3]);
        $this->oferta($productora, ['Producción audiovisual', 'Animación digital', 'Diseño gráfico y diagramación'], ['laboral' => 2, 'profesional' => 1]);
        $this->oferta($fundacion, ['Diseño y desarrollo web', 'Publicidad y marketing digital'], ['profesional' => 1]);
        $this->oferta($corfo, ['Diseño gráfico y diagramación'], ['profesional' => 1]);

        $paula = $this->supervisor($municipalidad, 'Paula Contreras', 'pcontreras@muniarica.cl', 'Encargada de Diseño', 'Dirección de Comunicaciones');
        $sergio = $this->supervisor($productora, 'Sergio Paredes', 'sparedes@nortevisual.cl', 'Director de Arte', 'Producción');
        $carolina = $this->supervisor($fundacion, 'Carolina Mamani Ticona', 'cmamani@fundacionuta.cl', 'Jefa de Comunicaciones', 'Comunicaciones');
        $rodrigo = $this->supervisor($corfo, 'Rodrigo Álvarez Soto', 'ralvarez@corfo.cl', 'Ejecutivo de Fomento', 'Dirección Regional');

        // Prácticas confirmadas: aparecen en Documentos y en la ficha de su centro
        $this->practica('21.334.120-0', 'Camila Rojas Pizarro', 'camila.rojas', 2025, 'Laboral I', $paula, '2026-08-17', '2026-09-04', ['Diseño gráfico y diagramación', 'Fotografía'], 'confirmada');
        $this->practica('21.502.611-6', 'Antonia Vega Muñoz', 'antonia.vega', 2024, 'Laboral II', $sergio, '2026-09-14', '2026-10-02', ['Animación digital'], 'confirmada');
        $this->practica('21.087.552-2', 'Josefa Ramírez Tapia', 'josefa.ramirez', 2022, 'Profesional', $carolina, '2026-09-07', '2026-11-27', ['Diseño y desarrollo web'], 'confirmada');
        $this->practica('21.245.678-0', 'Diego Choque Vargas', 'diego.choque', 2022, 'Profesional', $rodrigo, '2026-09-21', '2026-12-11', ['Diseño gráfico y diagramación'], 'confirmada');
        $this->practica($this->rut('21145220'), 'Pedro Lagos Rivas', 'pedro.lagos', 2022, 'Profesional', $sergio, '2026-08-17', '2026-11-27', ['Producción audiovisual'], 'confirmada');

        // Bandeja de la Coordinadora
        $inicio = today()->addWeeks(2)->toDateString();
        $fin = today()->addWeeks(14)->toDateString();
        $this->practica($this->rut('21360744'), 'Ignacia Torres Bravo', 'ignacia.torres', 2022, 'Profesional', $paula, $inicio, $fin, ['Fotografía'], 'por_revisar');
        $this->practica($this->rut('21298310'), 'Martín López Olivares', 'martin.lopez', 2022, 'Profesional', $paula, $inicio, $fin, ['Diseño gráfico y diagramación'], 'devuelta', 'Precisa las tareas de diseño y revisa el horario con el centro');

        // Solicitud con centro y supervisor propuestos: se activan con el acuerdo y la aprobación en la ficha
        $pixel = CentroPractica::firstOrCreate(['rut' => $this->rut('76333444')], [
            'razon_social' => 'Estudio Pixel Norte SpA', 'nombre' => 'Estudio Pixel Norte', 'direccion' => 'Sotomayor 1020, Arica', 'fecha_alta' => null,
        ]);
        $this->oferta($pixel, ['Diseño y desarrollo web'], []);
        $lorena = Supervisor::firstOrCreate(['centro_id' => $pixel->id, 'correo' => 'ldiaz@pixelnorte.cl'], [
            'nombre' => 'Lorena Díaz', 'cargo' => 'Directora de arte', 'unidad' => 'Diseño', 'fecha_alta' => null,
        ]);
        $this->practica($this->rut('21410255'), 'Tomás Araya Molina', 'tomas.araya', 2022, 'Profesional', $lorena, $inicio, $fin, ['Diseño y desarrollo web'], 'por_revisar', null, true);

        // Habilitados para una práctica laboral y sin centro (HU-08)
        $this->habilitar($this->rut('21566130'), 'Fernanda Quispe Apaza', 'fernanda.quispe', 2024, 'Laboral I', ['Fotografía', 'Producción audiovisual']);
        $this->habilitar($this->rut('21490872'), 'Javier Mamani Condori', 'javier.mamani', 2024, 'Laboral I', ['Producción audiovisual']);
    }

    private function habilitar(string $rut, string $nombre, string $correo, int $anio, string $practica, array $areas): Habilitacion
    {
        $usuario = User::firstOrCreate(['rut' => $rut], [
            'name' => $nombre, 'email' => "{$correo}@alumnos.uta.cl", 'password' => 'password',
        ]);
        $estudiante = Estudiante::firstOrCreate(['user_id' => $usuario->id], ['anio_ingreso' => $anio]);
        $estudiante->areas()->syncWithoutDetaching($this->areas($areas));
        $tipo = TipoPractica::where('nombre', $practica)->whereHas('malla', fn ($q) => $q->whereNotNull('vigente_desde'))->firstOrFail();

        return Habilitacion::firstOrCreate(
            ['estudiante_id' => $estudiante->id, 'tipo_practica_id' => $tipo->id, 'periodo_id' => $this->periodo->id],
            ['cumple_prerrequisito' => true, 'horas_requeridas' => $tipo->horas_requeridas],
        );
    }

    private function practica(string $rut, string $nombre, string $correo, int $anio, string $tipo, Supervisor $supervisor, string $inicio, string $fin, array $areas, string $estado, ?string $motivo = null, bool $propuesta = false): void
    {
        $habilitacion = $this->habilitar($rut, $nombre, $correo, $anio, $tipo, $areas);
        $esSolicitud = $tipo === 'Profesional';

        $practica = Practica::create([
            'habilitacion_id' => $habilitacion->id,
            'supervisor_id' => $supervisor->id,
            'origen' => $esSolicitud ? 'solicitud' : 'asignacion',
            'centro_propuesto_en_solicitud' => $propuesta,
            'supervisor_propuesto_en_solicitud' => $propuesta,
            'modalidad' => 'presencial',
            'tareas' => $esSolicitud ? 'Diseño de piezas gráficas y apoyo en la producción de contenidos digitales.' : null,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
        ]);

        if ($esSolicitud) {
            foreach ([1, 2, 3, 4, 5] as $dia) {
                $practica->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => '09:00', 'hora_fin' => '13:00', 'vigente_desde' => $inicio]);
            }
            $this->historial($practica, $habilitacion->estudiante->user_id, 'por_revisar', null, 4);
        }

        if ($estado !== 'por_revisar') {
            $this->historial($practica, $this->coordinadora->id, $estado, $motivo, 2);
        }
    }

    private function historial(Practica $practica, int $usuarioId, string $estado, ?string $motivo, int $diasAtras): void
    {
        HistorialPractica::create([
            'practica_id' => $practica->id, 'user_id' => $usuarioId, 'fecha_hora' => now()->subDays($diasAtras),
            'estado_nuevo' => $estado, 'motivo' => $motivo,
        ]);
    }

    // Áreas que ofrece el centro y cupos del período por categoría
    private function oferta(CentroPractica $centro, array $areas, array $cupos): void
    {
        $ids = $this->areas($areas);
        $centro->areas()->syncWithoutDetaching($ids);

        foreach ($cupos as $categoria => $cantidad) {
            RequerimientoCentro::firstOrCreate(
                ['centro_id' => $centro->id, 'periodo_id' => $this->periodo->id, 'categoria' => $categoria],
                ['cupos' => $cantidad],
            )->areas()->syncWithoutDetaching($ids);
        }
    }

    private function areas(array $nombres): array
    {
        return array_map(fn (string $nombre) => Area::firstOrCreate(['nombre' => $nombre])->id, $nombres);
    }

    private function rut(string $cuerpo): string
    {
        return Rut::normalizar($cuerpo.Rut::digitoVerificador($cuerpo));
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
