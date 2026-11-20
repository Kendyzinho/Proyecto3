<?php

namespace App\Services;

use App\Mail\DocumentosPractica;
use App\Models\CentroPractica;
use App\Models\Documento;
use App\Models\Malla;
use App\Models\Periodo;
use App\Models\Practica;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// HU-10: carta de presentación y guía del seguro escolar en PDF, y su envío al estudiante y al centro.
// Sin acuerdo de colaboración vigente no se generan (Art. 15). También genera el acuerdo que el centro firma.
class DocumentoService
{
    public const TIPOS = ['carta', 'seguro'];

    // Pie de página del formato oficial de la Carrera (acuerdos de colaboración)
    public const PIE = 'Avda. 18 de septiembre 2222 - Teléfono 58-2205624 - dimul@gestion.uta.cl';

    // Prácticas aprobadas del período, con el estado de cada documento
    public function listar(Periodo $periodo): Collection
    {
        return $this->consulta()
            ->whereHas('habilitacion', fn ($q) => $q->where('periodo_id', $periodo->id))
            ->whereNotNull('supervisor_id')
            ->formalizadas()
            ->get()
            ->sortBy(fn (Practica $p) => $p->habilitacion->estudiante->usuario->name)
            ->values()
            ->map(fn (Practica $p) => $this->resumen($p));
    }

    // Todo lo que muestra la pantalla para una práctica: estados, carta, guía y acuerdo del centro
    public function detalle(Practica $practica): array
    {
        $practica->loadMissing(['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro']);
        $centro = $practica->supervisor->centro;
        $acuerdo = $centro->acuerdoActual();

        return $this->resumen($practica) + [
            'textos' => [
                'carta' => $this->contenidoCarta($practica),
                'seguro' => $this->contenidoSeguro($practica),
            ],
            'centro_id' => $centro->id,
            'centro_interno' => $centro->es_interno,
            'acuerdo' => $acuerdo ? [
                'id' => $acuerdo->id,
                'tipo' => $acuerdo->tipo,
                'representante_legal' => $acuerdo->representante_legal,
                'fecha_inicio' => $acuerdo->fecha_inicio?->toDateString(),
                'fecha_termino' => $acuerdo->fecha_termino?->toDateString(),
                'estado' => $acuerdo->estado(),
                'tiene_archivo' => $acuerdo->archivo_pdf !== null,
            ] : null,
        ];
    }

    // Genera el PDF y lo registra; cada generación queda como un documento nuevo
    public function generar(Practica $practica, string $tipo, User $usuario): Documento
    {
        $practica->loadMissing(['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro']);
        $this->exigirAcuerdo($practica);

        $vista = $tipo === 'carta' ? 'pdf.carta' : 'pdf.seguro';
        $contenido = $tipo === 'carta' ? $this->contenidoCarta($practica) : $this->contenidoSeguro($practica);
        // Con subconjunto de la fuente el PDF pesa decenas de kB y no más de 1 MB
        $pdf = Pdf::loadView($vista, ['contenido' => $contenido])
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();

        $ruta = "documentos/{$practica->id}/{$tipo}_".now()->format('Ymd_His').'.pdf';
        Storage::put($ruta, $pdf);

        return Documento::create([
            'practica_id' => $practica->id,
            'user_id' => $usuario->id,
            'tipo' => $tipo,
            'archivo_o_url' => $ruta,
            'fecha' => now(),
        ]);
    }

    // Último documento de ese tipo; si no existe, lo genera
    public function vigente(Practica $practica, string $tipo, User $usuario): Documento
    {
        return $this->ultimo($practica, $tipo) ?? $this->generar($practica, $tipo, $usuario);
    }

    // Envía la carta y la guía al estudiante y al supervisor(a), y marca la fecha de envío
    public function enviar(Practica $practica, User $usuario): array
    {
        $practica->loadMissing(['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro']);
        $this->exigirAcuerdo($practica);

        $documentos = collect(self::TIPOS)->map(fn ($tipo) => $this->vigente($practica, $tipo, $usuario));
        $destinatarios = [$practica->habilitacion->estudiante->usuario->email, $practica->supervisor->correo];

        Mail::to($destinatarios)->send(new DocumentosPractica($practica, $documentos->all()));

        DB::transaction(fn () => $documentos->each(fn (Documento $d) => $d->update(['fecha_envio' => now()])));

        return $destinatarios;
    }

    private function consulta()
    {
        return Practica::with(['habilitacion.estudiante.usuario', 'habilitacion.tipoPractica', 'supervisor.centro', 'documentos']);
    }

    private function resumen(Practica $practica): array
    {
        $usuario = $practica->habilitacion->estudiante->usuario;
        $centro = $practica->supervisor->centro;
        $conAcuerdo = $centro->puedeRecibirEstudiantes();

        return [
            'id' => $practica->id,
            'estudiante' => $usuario->name,
            'correo' => $usuario->email,
            'practica' => $practica->habilitacion->tipoPractica->nombre,
            'centro' => $centro->nombre,
            'supervisor' => $practica->supervisor->nombre,
            'correo_supervisor' => $practica->supervisor->correo,
            'con_acuerdo' => $conAcuerdo,
            'carta' => $this->estado($practica, 'carta', $conAcuerdo),
            'seguro' => $this->estado($practica, 'seguro', $conAcuerdo),
        ];
    }

    // por_generar, generada o enviada; sin acuerdo la carta queda "sin_acuerdo" y el seguro "en_espera"
    private function estado(Practica $practica, string $tipo, bool $conAcuerdo): array
    {
        $documento = $this->ultimo($practica, $tipo);

        $estado = match (true) {
            $documento?->fecha_envio !== null => 'enviada',
            $documento !== null => 'generada',
            ! $conAcuerdo => $tipo === 'carta' ? 'sin_acuerdo' : 'en_espera',
            default => 'por_generar',
        };

        return ['estado' => $estado, 'documento_id' => $documento?->id];
    }

    private function ultimo(Practica $practica, string $tipo): ?Documento
    {
        $documentos = $practica->relationLoaded('documentos') ? $practica->documentos : $practica->documentos()->get();

        $documento = $documentos->where('tipo', $tipo)->sortByDesc('id')->first();

        // La carta indica el período: si las fechas cambiaron después de generarla, hay que generarla de nuevo
        return $tipo === 'carta' && $documento?->fecha->lt($practica->updated_at) ? null : $documento;
    }

    private function exigirAcuerdo(Practica $practica): void
    {
        if (! $practica->supervisor->centro->puedeRecibirEstudiantes()) {
            throw ValidationException::withMessages([
                'acuerdo' => 'El centro no tiene un acuerdo de colaboración vigente (Art. 15).',
            ]);
        }
    }

    // Texto de la carta de presentación. Lo usan el PDF y la vista previa de la pantalla
    public function contenidoCarta(Practica $practica): array
    {
        $habilitacion = $practica->habilitacion;
        $usuario = $habilitacion->estudiante->usuario;
        $tipo = $habilitacion->tipoPractica;
        $jefe = User::where('rol', 'jefe')->whereNull('fecha_baja')->orderBy('id')->first();

        $periodo = $practica->fecha_inicio && $practica->fecha_fin
            ? ' entre el '.$this->fecha($practica->fecha_inicio, false).' y el '.$this->fecha($practica->fecha_fin)
            : '';

        return [
            'titulo' => 'Carta de presentación',
            'lugar_fecha' => 'Arica, '.$this->fecha(now()),
            'destinatario' => [
                $practica->supervisor->nombre,
                'Supervisor(a) de Prácticas',
                $practica->supervisor->centro->nombre,
            ],
            'parrafos' => [
                "Presento a {$usuario->name}, RUT {$usuario->rut}, estudiante de Diseño Multimedia, para realizar su "
                    ."Práctica {$tipo->nombre} de {$habilitacion->horas_requeridas} horas{$periodo}, en las tareas acordadas con su institución.",
                'Cuenta con el seguro escolar (Ley 16.744) durante todo el período de práctica.',
            ],
            'firma' => [$jefe?->name ?? 'Jefe(a) de Carrera', 'Jefe de Carrera - Diseño Multimedia', 'Universidad de Tarapacá'],
            'pie' => self::PIE,
        ];
    }

    // Guía del seguro escolar con los datos de la práctica. Texto base a validar con la Coordinación
    public function contenidoSeguro(Practica $practica): array
    {
        $usuario = $practica->habilitacion->estudiante->usuario;
        $centro = $practica->supervisor->centro;
        $coordinadora = User::where('rol', 'coordinadora')->whereNull('fecha_baja')->orderBy('id')->first();

        return [
            'titulo' => 'Guía del seguro escolar',
            'lugar_fecha' => 'Arica, '.$this->fecha(now()),
            'datos' => [
                'Estudiante' => "{$usuario->name}, RUT {$usuario->rut}",
                'Práctica' => $practica->habilitacion->tipoPractica->nombre,
                'Centro de práctica' => $centro->nombre.($centro->direccion ? ", {$centro->direccion}" : ''),
                'Supervisor(a)' => $practica->supervisor->nombre,
                'Coordinación de Prácticas' => $coordinadora ? "{$coordinadora->name}, {$coordinadora->email}" : 'Coordinación de Prácticas',
            ],
            'secciones' => [
                [
                    'titulo' => 'Qué cubre',
                    'texto' => 'Durante la práctica estás protegido(a) por el seguro escolar de accidentes de la Ley 16.744 '
                        .'y el Decreto Supremo 313. Cubre los accidentes que ocurran a causa o con ocasión de la práctica '
                        .'y los del trayecto directo entre tu casa y el centro de práctica.',
                ],
                [
                    'titulo' => 'Qué hacer ante un accidente',
                    'pasos' => [
                        'Avisa de inmediato a tu supervisor(a) en el centro y a la Coordinación de Prácticas.',
                        'Acude a un servicio de salud público (hospital, SAPU o Cesfam) e indica que es un accidente escolar.',
                        'Pide a la Universidad la Declaración Individual de Accidente Escolar y preséntala en el servicio de salud.',
                    ],
                ],
                [
                    'titulo' => 'Atención',
                    'texto' => 'La atención médica, los medicamentos y la rehabilitación que requiera el accidente son gratuitos '
                        .'en la red pública de salud.',
                ],
            ],
            'pie' => self::PIE,
        ];
    }

    // Acuerdo de colaboración para firmar, con el formato oficial de la Carrera. No se guarda: el acuerdo
    // queda registrado cuando se sube firmado, así un borrador nunca reemplaza a un acuerdo vigente
    public function generarAcuerdo(
        CentroPractica $centro, string $tipo, string $representante, CarbonInterface $inicio, CarbonInterface $termino,
    ): string {
        return Pdf::loadView('pdf.acuerdo', ['contenido' => $this->contenidoAcuerdo($centro, $tipo, $representante, $inicio, $termino)])
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    // Texto de los formatos "Acuerdo de colaboración Prácticas Profesionales" (con equipamiento) y
    // "sin equipamiento" que entregó la Coordinación. El formato decía Ley 19.744: la del seguro escolar es la 16.744.
    // El formato no traía vigencia; se agrega porque el acuerdo indica cuándo empieza y termina (reunión 4)
    public function contenidoAcuerdo(
        CentroPractica $centro, string $tipo, string $representante, CarbonInterface $inicio, CarbonInterface $termino,
    ): array {
        $jefe = User::where('rol', 'jefe')->whereNull('fecha_baja')->orderBy('id')->first();
        $horas = Malla::vigente()?->tiposPractica()->where('categoria', 'profesional')->whereNull('fecha_baja')->orderBy('id')
            ->get()->map(fn ($practica) => "Práctica {$practica->nombre} ({$practica->horas_requeridas} horas)")->all() ?? [];

        $carrera = [
            ['texto' => 'Designar un/a Coordinador/a de prácticas como representante de la Carrera para fiscalizar y supervisar el cumplimiento del presente Acuerdo de Cooperación.'],
            ['texto' => 'Designar estudiantes para desarrollar Prácticas Profesionales.'],
            ['texto' => 'Que el/las estudiante practicante desarrolle soluciones del ámbito del diseño y afines como Fotografía, Ilustración, Diseño y Diagramación, Diseño Web y Video.'],
            ['texto' => 'Colaborar en la publicidad de las actividades a desarrollar en conjunto, que sean coherentes con la imagen corporativa de la carrera.'],
        ];
        if ($tipo === 'con_equipamiento') {
            $carrera[] = ['texto' => 'En la medida de que exista disponibilidad, facilitar el uso de equipamiento específico para que el/la estudiante pueda realizar actividades que lo requieran.'];
        }
        $carrera[] = ['texto' => 'La evaluación conjunta del desempeño de los alumnos practicantes.'];
        $carrera[] = ['texto' => 'Asegurar a nuestros alumnos, quienes se acogen a la Ley N° 16.744, que cubre cualquier accidente que pueda ocurrir durante el periodo de práctica.'];

        $horasPractica = ['texto' => 'Evidenciar el cumplimiento de las horas de práctica'];
        if ($horas !== []) {
            $horasPractica['detalle'] = $horas;
        }

        return [
            'titulo' => ['Acuerdo de colaboración de prácticas profesionales', 'Carrera de Diseño Multimedia – '.$centro->nombre],
            'lugar_fecha' => 'Arica, ________________ de '.now()->year,
            'parrafos' => [
                'El presente documento tiene por objeto establecer los compromisos que asumen la Carrera de Diseño Multimedia de la '
                    .'Universidad de Tarapacá y el Centro de Prácticas, para el adecuado desarrollo de las prácticas curriculares de los '
                    .'y las estudiantes, favoreciendo un proceso formativo que permita la aplicación de las competencias adquiridas '
                    .'durante su formación académica, resguardando el cumplimiento de los objetivos de aprendizaje y las condiciones '
                    .'necesarias para su correcta ejecución.',
                'A través de este acuerdo, las partes establecen los siguientes compromisos:',
            ],
            'compromisos' => [
                ['titulo' => 'La Carrera se compromete a:', 'items' => $carrera],
                ['titulo' => 'El Centro de Práctica se compromete a:', 'items' => [
                    ['texto' => 'Asignar labores profesionales respetando el nivel de competencias del estudiante.'],
                    ['texto' => 'La generación/habilitación de espacios físicos adecuados para el correcto trabajo de práctica.'],
                    ['texto' => 'Dar buen trato a los/las estudiantes.'],
                    ['texto' => 'Evaluar el desempeño de los estudiantes practicantes.'],
                    ['texto' => 'Designar a un/a Supervisor/a de Práctica que guíe las labores del estudiante.'],
                    $horasPractica,
                    ['texto' => 'Informar, a través de el/la Supervisor/a de Práctica, cualquier irregularidad o incumplimiento por parte del estudiante practicante.'],
                ]],
            ],
            'vigencia' => 'El presente acuerdo tendrá vigencia desde el '.$this->fecha($inicio).' hasta el '.$this->fecha($termino).'.',
            'firmas' => [
                [$centro->nombre, $representante, 'Representante, cargo y firma'],
                [$jefe?->name ?? 'Jefe(a) de Carrera', 'Jefe de Carrera - Diseño Multimedia', 'Universidad de Tarapacá'],
            ],
            'pie' => self::PIE,
        ];
    }

    // "1 de septiembre de 2026"; sin año cuando ya va al final de la frase
    private function fecha(CarbonInterface $fecha, bool $conAnio = true): string
    {
        return $fecha->locale('es')->translatedFormat($conAnio ? 'j \d\e F \d\e Y' : 'j \d\e F');
    }
}
