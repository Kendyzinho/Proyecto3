<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\Opcion;
use App\Models\PlantillaInstrumento;
use App\Models\TipoPractica;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Instrumentos de HU-16 (pauta de visita) y HU-19 (informe confidencial del supervisor).
// Se puede correr de nuevo: un tipo de práctica creado después (HU-06) recibe su informe sin duplicar el resto.
class InstrumentoSeeder extends Seeder
{
    private const VIGENTE_DESDE = '2026-01-01';

    // Sin formato oficial: criterios acordados para el Release 2 (confirmar con la Coordinadora)
    private const PAUTA = [
        'condiciones' => 'El centro entrega las condiciones de trabajo y seguridad acordadas',
        'pertinencia' => 'Las tareas son pertinentes al tipo de práctica',
        'acompanamiento' => 'El supervisor(a) orienta y supervisa al estudiante',
        'bitacora_asistencia' => 'La bitácora y la asistencia están al día',
        'desempeno' => 'Desempeño observado del estudiante',
        'relacion_equipo' => 'Relación del estudiante con el equipo del centro',
    ];

    // Formulario oficial del informe confidencial (Google Forms 2026), iguales en todas las prácticas
    private const TRANSVERSALES = [
        'Puntualidad coherente con los horarios programados',
        'Organización para cumplir las tareas en los plazos establecidos',
        'Capacidad reflexiva y autocrítica',
        'Comportamiento ético acorde a las normas del centro',
        'Habilidades comunicativas y de trabajo en equipo',
        'Adaptabilidad a contextos cambiantes',
        'Actitud proactiva ante los desafíos',
        'Capacidad de aprendizaje según las expectativas',
        'Capacidad creativa',
        'Cantidad de tareas acorde a lo solicitado y a las :horas horas de dedicación',
        'Calidad esperada en los trabajos entregados',
        'Uso y aplicación de herramientas tecnológicas',
    ];

    private const ESCALA_ACUERDO = [
        1 => 'Totalmente en desacuerdo',
        2 => 'En desacuerdo',
        3 => 'Ni de acuerdo ni en desacuerdo',
        4 => 'De acuerdo',
        5 => 'Totalmente de acuerdo',
    ];

    private const DISCIPLINARES = [
        'profesional' => [
            'Ilustraciones digitales con calidad',
            'Piezas de identidad gráfica (logotipos, isologos, isotipos, imagotipos)',
            'Afiches con composición, color y tipografía',
            'Diseño editorial de calidad',
            'Fotografía digital profesional con conocimiento técnico avanzado',
            'Edición y producción de video avanzada',
            'Uso de herramientas de UX/UI',
            'Animación y motion graphics',
            'Gestión de contenidos en redes sociales',
        ],
        'laboral' => [
            'Ilustraciones digitales con calidad',
            'Piezas de identidad gráfica',
            'Afiches con composición, color y tipografía',
            'Fotografía digital semiprofesional (composición y exposición)',
            'Edición y producción de video básica',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->pauta();
            TipoPractica::orderBy('id')->get()->each(fn (TipoPractica $tipo) => $this->informe($tipo));
        });
    }

    private function pauta(): void
    {
        $plantilla = $this->plantilla('pauta', null);
        $orden = 0;
        foreach (self::PAUTA as $clave => $enunciado) {
            $this->item($plantilla, $clave, $enunciado, 'escala', true, 'pauta', ++$orden, conNoAplica: false);
        }
        $this->item($plantilla, 'comentarios', 'Comentarios de la Coordinadora', 'texto', true, 'pauta', ++$orden);
    }

    private function informe(TipoPractica $tipo): void
    {
        $plantilla = $this->plantilla('informe', $tipo->id);
        $orden = 0;
        foreach (self::TRANSVERSALES as $i => $enunciado) {
            $texto = str_replace(':horas', (string) $tipo->horas_requeridas, $enunciado);
            $this->item($plantilla, sprintf('t%02d', $i + 1), $texto, 'escala', true, 'transversal', ++$orden, conNoAplica: true);
        }
        foreach (self::DISCIPLINARES[$tipo->categoria] ?? [] as $i => $enunciado) {
            $this->item($plantilla, sprintf('d%02d', $i + 1), $enunciado, 'escala', true, 'disciplinar', ++$orden, conNoAplica: true);
        }
        $this->item($plantilla, 'comentario', 'Comentario sobre el desempeño del estudiante', 'texto', false, 'cierre', ++$orden);
    }

    private function plantilla(string $instrumento, ?int $tipoPracticaId): PlantillaInstrumento
    {
        return PlantillaInstrumento::firstOrCreate([
            'instrumento' => $instrumento, 'tipo_practica_id' => $tipoPracticaId, 'vigente_desde' => self::VIGENTE_DESDE,
        ]);
    }

    private function item(PlantillaInstrumento $plantilla, string $clave, string $enunciado, string $tipo, bool $obligatorio, string $seccion, int $orden, bool $conNoAplica = false): void
    {
        $item = Item::updateOrCreate(['plantilla_id' => $plantilla->id, 'clave' => $clave], [
            'enunciado' => $enunciado, 'tipo_respuesta' => $tipo, 'obligatorio' => $obligatorio, 'seccion' => $seccion, 'orden' => $orden,
        ]);
        if ($tipo !== 'escala') {
            return;
        }
        // El informe usa la escala de acuerdo del formulario oficial; la pauta, números
        foreach (range(1, 5) as $valor) {
            $etiqueta = $conNoAplica ? self::ESCALA_ACUERDO[$valor] : (string) $valor;
            Opcion::updateOrCreate(['item_id' => $item->id, 'orden' => $valor], ['etiqueta' => $etiqueta, 'valor_numerico' => $valor]);
        }
        if ($conNoAplica) {
            Opcion::updateOrCreate(['item_id' => $item->id, 'orden' => 6], ['etiqueta' => 'No aplica', 'valor_numerico' => null]);
        }
    }
}
