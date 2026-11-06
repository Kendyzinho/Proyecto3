import { Component, TemplateRef, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule } from '@angular/material/paginator';
import { MatSelectModule } from '@angular/material/select';

import { AcuerdoCentro } from '../../secretaria/documentos/acuerdo-centro';

import {
  CentroPractica,
  CupoCentro,
  EstadoAcuerdo,
  EstadoCentro,
  EstudianteCentro,
  EstadoPractica,
  ResultadoDictamen,
  SupervisorCentro,
} from './centro-practica.model';
import { CentrosPracticaService } from './centros-practica.service';

type FiltroCentro = 'Todos' | EstadoAcuerdo | 'Propuestos' | 'Dados de baja';
type CampoCentro = 'nombre' | 'razonSocial' | 'rut';
type PeriodoCupos = { periodo: string; cupos: CupoCentro[] };
type SupervisorFormulario = { nombre: string; correo: string; cargo: string; unidad: string; telefono: string };

const AREAS_BASE = [
  'Diseño y desarrollo web',
  'Diseño gráfico y diagramación',
  'Fotografía',
  'Producción audiovisual',
  'Animación digital',
  'Publicidad y marketing digital',
  'Diseño editorial',
];

@Component({
  selector: 'app-centros-practica',
  imports: [FormsModule, AcuerdoCentro, MatButtonModule, MatDialogModule, MatFormFieldModule, MatIconModule, MatInputModule, MatPaginatorModule, MatSelectModule],
  templateUrl: './centros-practica.html',
  styleUrl: './centros-practica.scss',
})
export class CentrosPractica {
  private readonly centrosService = inject(CentrosPracticaService);
  private readonly dialogo = inject(MatDialog);
  private readonly centros = signal<CentroPractica[]>([]);
  private readonly plantillaCentro = viewChild.required<TemplateRef<unknown>>('plantillaCentro');
  private readonly plantillaBaja = viewChild.required<TemplateRef<unknown>>('plantillaBaja');
  private readonly plantillaAcuerdo = viewChild.required<TemplateRef<unknown>>('plantillaAcuerdo');
  private readonly plantillaEstudiante = viewChild.required<TemplateRef<unknown>>('plantillaEstudiante');
  private dialogoAbierto: MatDialogRef<unknown> | null = null;

  protected readonly filtros: { valor: FiltroCentro; texto: string }[] = [
    { valor: 'Todos', texto: 'Todos' },
    { valor: 'Vigente', texto: 'Acuerdo vigente' },
    { valor: 'En trámite', texto: 'Acuerdo en trámite' },
    { valor: 'Vencido', texto: 'Acuerdo vencido' },
    { valor: 'Sin acuerdo', texto: 'Sin acuerdo' },
    { valor: 'Propuestos', texto: 'Propuestos' },
    { valor: 'Dados de baja', texto: 'Dados de baja' },
  ];
  protected readonly tamanoPagina = 10;
  protected readonly pagina = signal(0);

  protected readonly errorApi = signal('');
  protected readonly guardando = signal(false);
  protected readonly areasCatalogo = signal<string[]>([]);
  protected readonly periodosCatalogo = signal<{ id: number; anio: number; semestre: number }[]>([]);

  protected readonly busqueda = signal('');
  protected readonly filtro = signal<FiltroCentro>('Todos');
  protected readonly areaSeleccionada = signal('Todas');
  protected readonly centroSeleccionadoId = signal<number | null>(null);
  protected readonly centroEditandoId = signal<number | null>(null);
  protected readonly areasSeleccionadas = signal<string[]>([]);
  protected readonly nuevaArea = signal('');
  protected readonly intentoGuardar = signal(false);
  protected readonly estudianteSeleccionado = signal<EstudianteCentro | null>(null);
  protected readonly motivoBaja = signal('');
  protected readonly errorBaja = signal('');
  protected readonly mostrarFormularioSupervisor = signal(false);
  protected readonly guardandoSupervisor = signal(false);
  protected readonly intentoGuardarSupervisor = signal(false);
  protected readonly supervisorFichaError = signal('');
  protected readonly nuevoSupervisorFicha = signal<SupervisorFormulario>(this.supervisorVacio());
  protected readonly cuposFormulario = signal<CupoCentro[]>([]);
  protected readonly supervisoresFormulario = signal<SupervisorFormulario[]>([]);
  protected periodoNuevoCupoId: number | null = null;
  protected categoriaNuevoCupo: 'Laboral' | 'Profesional' = 'Laboral';
  protected cantidadNuevoCupo = 1;

  protected nuevoCentro = this.formularioVacio();
  private centroOriginalAlEditar: string | null = null;

  protected readonly centrosVisibles = computed(() => {
    const termino = this.busqueda().trim().toLocaleLowerCase('es');
    return this.centros().filter((centro) => {
      const coincideTexto =
        !termino ||
        [
          centro.nombre,
          centro.razonSocial,
          centro.rut,
          ...centro.supervisores.map((s) => s.nombre),
        ].some((valor) => valor.toLocaleLowerCase('es').includes(termino));
      const coincideArea =
        this.areaSeleccionada() === 'Todas' || centro.areas.includes(this.areaSeleccionada());
      return coincideTexto && coincideArea && this.cumpleFiltro(centro);
    });
  });

  protected readonly centrosPagina = computed(() => {
    const desde = this.pagina() * this.tamanoPagina;
    return this.centrosVisibles().slice(desde, desde + this.tamanoPagina);
  });

  protected readonly areas = computed(() =>
    [...new Set([...this.areasCatalogo(), ...AREAS_BASE, ...this.centros().flatMap((centro) => centro.areas)])].sort(
      (a, b) => a.localeCompare(b, 'es'),
    ),
  );
  protected readonly centroSeleccionado = computed(
    () => this.centros().find((centro) => centro.id === this.centroSeleccionadoId()) ?? null,
  );
  protected readonly areasDisponiblesFormulario = computed(() =>
    [...new Set([...this.areasCatalogo(), ...AREAS_BASE, ...this.areasSeleccionadas()])].sort((a, b) =>
      a.localeCompare(b, 'es'),
    ),
  );
  protected readonly cuposAgrupados = computed<PeriodoCupos[]>(() => {
    const grupos = new Map<string, CupoCentro[]>();
    for (const cupo of this.centroSeleccionado()?.cupos ?? []) {
      grupos.set(cupo.periodo, [...(grupos.get(cupo.periodo) ?? []), cupo]);
    }
    return [...grupos.entries()].map(([periodo, cupos]) => ({ periodo, cupos }));
  });
  protected readonly tituloFormulario = computed(() =>
    this.centroEditandoId() === null ? 'Registrar nuevo centro' : 'Editar centro',
  );

  constructor() {
    this.cargarCatalogo();
  }

  protected buscar(valor: string): void {
    this.busqueda.set(valor);
    this.pagina.set(0);
  }

  protected seleccionarArea(area: string): void {
    this.areaSeleccionada.set(area);
    this.pagina.set(0);
  }

  private cumpleFiltro(centro: CentroPractica): boolean {
    switch (this.filtro()) {
      case 'Todos': return true;
      case 'Propuestos': return centro.estado === 'Propuesto';
      case 'Dados de baja': return centro.estado === 'Dado de baja';
      // El centro interno no necesita acuerdo: no cuenta como "sin acuerdo"
      default: return !centro.esInterno && centro.acuerdo === this.filtro();
    }
  }

  private abrir(plantilla: TemplateRef<unknown>, ancho: string): MatDialogRef<unknown> {
    this.dialogoAbierto = this.dialogo.open(plantilla, { width: ancho, maxWidth: 'calc(100vw - 24px)', maxHeight: '94dvh' });
    return this.dialogoAbierto;
  }

  protected reintentarCarga(): void {
    this.cargarCatalogo();
  }

  protected seleccionarFiltro(valor: FiltroCentro): void {
    this.filtro.set(valor);
    this.pagina.set(0);
  }

  protected seleccionarCentro(centro: CentroPractica): void {
    this.centroSeleccionadoId.set(centro.id);
  }

  protected volverAlCatalogo(): void {
    this.centroSeleccionadoId.set(null);
  }

  protected abrirFormularioNuevo(): void {
    this.limpiarFormulario();
    this.supervisoresFormulario.set([this.supervisorVacio()]);
    this.centroEditandoId.set(null);
    this.abrirFormularioCentro();
  }

  private abrirFormularioCentro(): void {
    this.abrir(this.plantillaCentro(), '760px').afterClosed().subscribe(() => this.limpiarFormulario());
  }

  protected editarCentro(): void {
    const centro = this.centroSeleccionado();
    if (!centro) return;
    this.errorApi.set('');
    this.limpiarFormulario();
    this.nuevoCentro = {
      nombre: centro.nombre,
      razonSocial: centro.razonSocial,
      rut: centro.rut,
      direccion: centro.direccion,
      telefono: centro.telefono,
      esInterno: centro.esInterno,
    };
    this.areasSeleccionadas.set([...centro.areas]);
    this.cuposFormulario.set([...centro.cupos]);
    this.centroEditandoId.set(centro.id);
    this.centroOriginalAlEditar = this.serializarDatosEditablesCentro();
    this.abrirFormularioCentro();
  }

  protected hayCambiosCentro(): boolean {
    return this.centroOriginalAlEditar === null ||
      this.serializarDatosEditablesCentro() !== this.centroOriginalAlEditar;
  }

  protected agregarSupervisorFormulario(): void {
    this.supervisoresFormulario.update((items) => [...items, this.supervisorVacio()]);
  }

  protected quitarSupervisorFormulario(indice: number): void {
    this.supervisoresFormulario.update((items) => items.filter((_, actual) => actual !== indice));
  }

  protected actualizarSupervisorFormulario(
    indice: number,
    campo: keyof SupervisorFormulario,
    valor: string,
  ): void {
    this.supervisoresFormulario.update((items) =>
      items.map((item, actual) => actual === indice ? { ...item, [campo]: valor } : item),
    );
  }

  protected errorSupervisor(indice: number, campo: 'nombre' | 'correo'): string {
    if (!this.intentoGuardar()) return '';
    const valor = this.supervisoresFormulario()[indice]?.[campo].trim() ?? '';
    if (!valor) return 'Este campo es obligatorio.';
    if (campo === 'correo' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) return 'Ingresa un correo válido.';
    return '';
  }

  protected supervisoresResumen(centro: CentroPractica): string {
    const vigentes = centro.supervisores.filter((supervisor) => supervisor.estado !== 'De baja');
    if (vigentes.length === 0) return 'Sin supervisor';
    if (vigentes.length > 1) return `${vigentes.length} supervisores`;
    return vigentes[0].estado === 'Propuesto' ? `${vigentes[0].nombre} (por aprobar)` : vigentes[0].nombre;
  }

  protected puedeActivarSupervisores(centro: CentroPractica): boolean {
    return centro.estado === 'Activo' && (centro.esInterno || centro.acuerdo === 'Vigente');
  }

  protected abrirFormularioSupervisor(): void {
    this.supervisorFichaError.set('');
    this.intentoGuardarSupervisor.set(false);
    this.nuevoSupervisorFicha.set(this.supervisorVacio());
    this.mostrarFormularioSupervisor.set(true);
  }

  protected cancelarSupervisorFicha(): void {
    this.mostrarFormularioSupervisor.set(false);
    this.supervisorFichaError.set('');
  }

  protected actualizarSupervisorFicha(campo: keyof SupervisorFormulario, valor: string): void {
    this.nuevoSupervisorFicha.update((actual) => ({ ...actual, [campo]: valor }));
    this.supervisorFichaError.set('');
  }

  protected nombreSupervisorFichaValido(): boolean {
    return !!this.nuevoSupervisorFicha().nombre.trim();
  }

  protected correoSupervisorFichaValido(): boolean {
    const correo = this.nuevoSupervisorFicha().correo.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return false;
    return !this.centroSeleccionado()?.supervisores.some((supervisor) => supervisor.correo.toLowerCase() === correo.toLowerCase());
  }

  protected mostrarErrorSupervisorFicha(valido: boolean): boolean {
    return this.intentoGuardarSupervisor() && !valido;
  }

  protected mensajeCorreoSupervisorFicha(): string {
    const correo = this.nuevoSupervisorFicha().correo.trim();
    if (!correo) return 'Ingresa el correo del supervisor o supervisora.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) return 'Ingresa un correo válido.';
    return 'Ya existe un supervisor con este correo en el centro.';
  }

  protected guardarSupervisorFicha(): void {
    const centro = this.centroSeleccionado();
    const supervisor = this.nuevoSupervisorFicha();
    if (!centro) return;
    this.intentoGuardarSupervisor.set(true);
    if (!this.nombreSupervisorFichaValido() || !this.correoSupervisorFichaValido()) return;

    this.guardandoSupervisor.set(true);
    this.supervisorFichaError.set('');
    this.centrosService.agregarSupervisor(centro.id, supervisor).subscribe({
      next: (actualizado) => {
        this.reemplazarCentro(actualizado);
        this.cancelarSupervisorFicha();
      },
      error: (error: unknown) => {
        this.guardandoSupervisor.set(false);
        this.supervisorFichaError.set(this.centrosService.mensajeError(error));
      },
      complete: () => this.guardandoSupervisor.set(false),
    });
  }

  protected cambiarEstadoSupervisor(supervisor: SupervisorCentro): void {
    const centro = this.centroSeleccionado();
    if (!centro || supervisor.id === undefined) return;
    if (supervisor.estado === 'Propuesto' && !this.puedeActivarSupervisores(centro)) {
      this.errorApi.set('Para activar al supervisor, primero el centro debe estar activo y tener acuerdo vigente.');
      return;
    }

    const activar = supervisor.estado === 'Propuesto';
    if (!activar && !window.confirm(`¿Dar de baja a ${supervisor.nombre}? Sus prácticas e historial se conservarán.`)) return;

    this.guardando.set(true);
    this.errorApi.set('');
    const accion = activar
      ? this.centrosService.activarSupervisor(centro.id, supervisor.id)
      : this.centrosService.darDeBajaSupervisor(centro.id, supervisor.id);
    accion.subscribe({
      next: (actualizado) => this.reemplazarCentro(actualizado),
      error: (error: unknown) => this.errorApi.set(this.centrosService.mensajeError(error)),
      complete: () => this.guardando.set(false),
    });
  }

  protected cerrarFormularioNuevo(): void {
    this.dialogoAbierto?.close();
  }

  protected actualizarCampo(
    campo: CampoCentro | 'direccion' | 'telefono',
    valor: string,
  ): void {
    this.nuevoCentro = { ...this.nuevoCentro, [campo]: valor };
  }

  protected cambiarTipoCentro(valor: string): void {
    this.nuevoCentro = { ...this.nuevoCentro, esInterno: valor === 'Interno' };
  }

  protected errorCampo(campo: CampoCentro): string {
    const valor = this.nuevoCentro[campo].trim();
    if (!valor && this.intentoGuardar()) return 'Este campo es obligatorio.';
    if (campo === 'rut' && valor && !this.esRutChilenoValido(valor))
      return 'Ingresa un RUT chileno válido (por ejemplo, 12.345.678-5).';
    if (campo === 'rut' && valor && this.rutDuplicado(valor))
      return 'Ya existe un centro registrado con este RUT.';
    return '';
  }

  protected errorTipoCentro(): string {
    const yaExisteInterno = this.centros().some(
      (centro) => centro.esInterno && centro.id !== this.centroEditandoId(),
    );
    return this.intentoGuardar() && this.nuevoCentro.esInterno && yaExisteInterno
      ? 'Ya existe un centro interno registrado. La UTA debe mantenerse como un único centro.'
      : '';
  }

  protected alternarArea(area: string): void {
    this.areasSeleccionadas.update((seleccionadas) =>
      seleccionadas.includes(area)
        ? seleccionadas.filter((item) => item !== area)
        : [...seleccionadas, area],
    );
  }

  protected agregarAreaPersonalizada(): void {
    const area = this.nuevaArea().trim();
    if (!area) return;
    if (
      !this.areasSeleccionadas().some(
        (item) => item.toLocaleLowerCase('es') === area.toLocaleLowerCase('es'),
      )
    ) {
      this.areasSeleccionadas.update((items) => [...items, area]);
    }
    this.nuevaArea.set('');
  }

  protected quitarArea(area: string): void {
    this.areasSeleccionadas.update((items) => items.filter((item) => item !== area));
  }

  protected agregarCupo(): void {
    const periodo = this.periodosCatalogo().find((item) => item.id === this.periodoNuevoCupoId);
    if (!periodo || !Number.isInteger(this.cantidadNuevoCupo) || this.cantidadNuevoCupo < 1) return;
    const cupo: CupoCentro = {
      periodo: `${periodo.semestre}º semestre ${periodo.anio}`,
      periodoId: periodo.id,
      categoria: this.categoriaNuevoCupo,
      total: this.cantidadNuevoCupo,
      ocupados: 0,
      libres: this.cantidadNuevoCupo,
    };
    this.cuposFormulario.update((cupos) => [
      ...cupos.filter((item) => item.periodoId !== cupo.periodoId || item.categoria !== cupo.categoria),
      cupo,
    ]);
  }

  protected quitarCupo(cupo: CupoCentro): void {
    this.cuposFormulario.update((cupos) => cupos.filter((item) => item !== cupo));
  }

  protected guardarCentro(): void {
    this.intentoGuardar.set(true);
    const hayError = (['nombre', 'razonSocial', 'rut'] as CampoCentro[]).some((campo) =>
      this.errorCampo(campo),
    );
    const erroresSupervisores = this.centroEditandoId() === null && (
      this.supervisoresFormulario().length === 0 || this.supervisoresFormulario().some((_, indice) =>
        !!this.errorSupervisor(indice, 'nombre') || !!this.errorSupervisor(indice, 'correo'))
    );
    if (hayError || this.errorTipoCentro() || this.areasSeleccionadas().length === 0 || erroresSupervisores) return;

    const centroEditando = this.centroEditandoId();
    if (centroEditando !== null && !this.hayCambiosCentro()) {
      this.errorApi.set('No hay cambios en el centro para guardar.');
      return;
    }
    const actual = this.centros().find((centro) => centro.id === centroEditando);
    const datos: CentroPractica = {
      id: centroEditando ?? 0,
      nombre: this.nuevoCentro.nombre.trim(),
      razonSocial: this.nuevoCentro.razonSocial.trim(),
      rut: this.nuevoCentro.rut.trim(),
      areas: this.areasSeleccionadas(),
      acuerdo: actual?.acuerdo ?? 'Sin acuerdo',
      cupos: this.cuposFormulario(),
      supervisorPrincipal: actual?.supervisorPrincipal ?? this.supervisoresFormulario()[0]?.nombre ?? null,
      estado: actual?.estado ?? 'Propuesto',
      direccion: this.nuevoCentro.direccion.trim(),
      telefono: this.nuevoCentro.telefono.trim(),
      esInterno: this.nuevoCentro.esInterno,
      representanteLegal: actual?.representanteLegal ?? null,
      tipoAcuerdo: actual?.tipoAcuerdo ?? null,
      vigenciaAcuerdo: actual?.vigenciaAcuerdo ?? null,
      supervisores: actual?.supervisores ?? this.supervisoresFormulario().map((supervisor) => ({
        ...supervisor,
        estado: 'Propuesto' as const,
      })),
      estudiantes: actual?.estudiantes ?? [],
      motivoBaja: actual?.motivoBaja,
      fechaAlta: actual?.fechaAlta ?? null,
    };

    this.guardando.set(true);
    this.errorApi.set('');
    this.centrosService.guardar(datos, centroEditando ?? undefined).subscribe({
      next: (guardado) => {
        this.centros.update((centros) =>
          centroEditando === null
            ? [guardado, ...centros]
            : centros.map((centro) => (centro.id === centroEditando ? guardado : centro)),
        );
        this.cerrarFormularioNuevo();
        this.areaSeleccionada.set('Todas');
        this.filtro.set('Todos');
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorApi.set(this.centrosService.mensajeError(error));
      },
      complete: () => this.guardando.set(false),
    });
  }

  protected iniciarBaja(): void {
    this.motivoBaja.set('');
    this.errorBaja.set('');
    this.abrir(this.plantillaBaja(), '480px');
  }

  protected cerrarBaja(): void {
    this.dialogoAbierto?.close();
    this.errorBaja.set('');
  }

  protected confirmarBaja(): void {
    const motivo = this.motivoBaja().trim();
    if (!motivo) {
      this.errorBaja.set('El motivo de baja es obligatorio.');
      return;
    }
    const id = this.centroSeleccionadoId();
    if (id === null) return;
    this.guardando.set(true);
    this.errorBaja.set('');
    this.centrosService.darDeBaja(id, motivo).subscribe({
      next: (centro) => {
        this.reemplazarCentro(centro);
        this.cerrarBaja();
      },
      error: (error: unknown) => {
        this.guardando.set(false);
        this.errorBaja.set(this.centrosService.mensajeError(error));
      },
      complete: () => this.guardando.set(false),
    });
  }

  protected cambiarEstadoCentro(): void {
    const centro = this.centroSeleccionado();
    if (!centro) return;
    if (centro.estado === 'Dado de baja') {
      this.errorApi.set('');
      this.guardando.set(true);
      this.centrosService.reactivar(centro.id).subscribe({
        next: (reactivado) => this.reemplazarCentro(reactivado),
        error: (error: unknown) => {
          this.guardando.set(false);
          this.errorApi.set(this.centrosService.mensajeError(error));
        },
        complete: () => this.guardando.set(false),
      });
      return;
    }
    this.iniciarBaja();
  }

  protected mostrarFichaEstudiante(estudiante: EstudianteCentro): void {
    this.estudianteSeleccionado.set(estudiante);
    this.abrir(this.plantillaEstudiante(), '420px');
  }

  protected abrirAcuerdo(): void {
    this.abrir(this.plantillaAcuerdo(), '640px');
  }

  // El acuerdo con PDF y vigencia activa el centro: se vuelve a leer el catálogo
  protected acuerdoGuardado(): void {
    this.dialogoAbierto?.close();
    this.cargarCatalogo();
  }

  protected textoTipoAcuerdo(tipo: string | null): string {
    if (!tipo) return 'Sin registrar';
    return tipo === 'con_equipamiento' ? 'Con equipamiento' : 'Tradicional';
  }

  protected textoCupos(cupos: CupoCentro[]): string {
    return cupos.map((cupo) => `${cupo.categoria} ${cupo.libres} de ${cupo.total}`).join(', ');
  }

  protected textoResultado(estudiante: EstudianteCentro): string {
    return estudiante.resultado ? this.etiquetaResultado(estudiante.resultado) : this.etiquetaEstadoPractica(estudiante.estado);
  }

  protected iniciales(nombre: string): string {
    return nombre
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map((parte) => parte[0]?.toLocaleUpperCase('es') ?? '')
      .join('');
  }

  protected etiquetaEstadoPractica(estado: EstadoPractica): string {
    const etiquetas: Record<EstadoPractica, string> = {
      por_revisar: 'Por revisar',
      devuelta: 'Devuelta',
      confirmada: 'Confirmada',
      en_curso: 'En curso',
      finalizada: 'Finalizada',
      cancelada: 'Cancelada',
    };
    return etiquetas[estado];
  }

  protected etiquetaResultado(resultado: ResultadoDictamen): string {
    if (resultado === 'aprobado') return 'Aprobada';
    if (resultado === 'reprobado') return 'Reprobada';
    return 'Pendiente de evaluación';
  }

  protected formatearRut(): void {
    const rut = this.nuevoCentro.rut
      .trim()
      .replace(/[.\-\s]/g, '')
      .toLocaleUpperCase('es');
    if (!/^\d+[0-9K]$/.test(rut)) return;
    const cuerpo = rut.slice(0, -1);
    const digito = rut.slice(-1);
    const cuerpoFormateado = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    this.actualizarCampo('rut', `${cuerpoFormateado}-${digito}`);
  }

  // Cupos libres del período actual, o del más reciente que tenga definido
  protected cuposLibres(centro: CentroPractica): string {
    const ids = centro.cupos.map((cupo) => cupo.periodoId ?? 0);
    if (!ids.length) return '—';
    const actual = this.periodoActual()?.id;
    const periodoId = actual !== undefined && ids.includes(actual) ? actual : Math.max(...ids);
    const delPeriodo = centro.cupos.filter((cupo) => cupo.periodoId === periodoId);
    return `${delPeriodo.reduce((suma, cupo) => suma + cupo.libres, 0)} de ${delPeriodo.reduce((suma, cupo) => suma + cupo.total, 0)}`;
  }

  // 1er semestre hasta junio y 2º desde julio, igual que Periodo::actual() en el backend
  private periodoActual() {
    const hoy = new Date();
    const semestre = hoy.getMonth() >= 6 ? 2 : 1;
    return this.periodosCatalogo().find((item) => item.anio === hoy.getFullYear() && item.semestre === semestre);
  }

  protected estadoCentroClass(estado: EstadoCentro): string {
    return estado === 'Activo'
      ? 'estado--exito'
      : estado === 'Propuesto'
        ? 'estado--info'
        : estado === 'Sin acuerdo'
          ? 'estado--advertencia'
          : 'estado--error';
  }

  protected verAcuerdoPdf(): void {
    const acuerdoId = this.centroSeleccionado()?.acuerdoArchivoId;
    if (!acuerdoId) {
      this.errorApi.set('Este centro todavía no tiene un PDF de acuerdo cargado.');
      return;
    }

    const ventana = window.open('', '_blank');
    if (!ventana) {
      this.errorApi.set('Permite las ventanas emergentes para ver el PDF del acuerdo.');
      return;
    }

    this.errorApi.set('');
    this.centrosService.archivoAcuerdo(acuerdoId).subscribe({
      next: (pdf) => {
        const url = URL.createObjectURL(pdf);
        ventana.location.href = url;
        window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
      },
      error: (error: unknown) => {
        ventana.close();
        this.errorApi.set(this.centrosService.mensajeError(error));
      },
    });
  }

  protected estadoAcuerdoClass(estado: EstadoAcuerdo): string {
    return estado === 'Vigente'
      ? 'estado--exito'
      : estado === 'En trámite'
        ? 'estado--advertencia'
        : estado === 'Vencido'
          ? 'estado--error'
          : 'estado--neutro';
  }

  private formularioVacio() {
    return {
      nombre: '',
      razonSocial: '',
      rut: '',
      direccion: '',
      telefono: '',
      esInterno: false,
    };
  }

  private supervisorVacio(): SupervisorFormulario {
    return { nombre: '', correo: '', cargo: '', unidad: '', telefono: '' };
  }

  private limpiarFormulario(): void {
    this.centroOriginalAlEditar = null;
    this.nuevoCentro = this.formularioVacio();
    this.areasSeleccionadas.set([]);
    this.nuevaArea.set('');
    this.cuposFormulario.set([]);
    this.supervisoresFormulario.set([]);
    this.intentoGuardar.set(false);
    this.periodoNuevoCupoId = this.periodoActual()?.id ?? this.periodosCatalogo()[0]?.id ?? null;
    this.categoriaNuevoCupo = 'Laboral';
    this.cantidadNuevoCupo = 1;
  }

  private serializarDatosEditablesCentro(): string {
    const cupos = [...this.cuposFormulario()]
      .map((cupo) => ({
        periodoId: cupo.periodoId,
        periodo: cupo.periodo,
        categoria: cupo.categoria,
        total: cupo.total,
      }))
      .sort((a, b) => `${a.periodoId}|${a.categoria}`.localeCompare(`${b.periodoId}|${b.categoria}`));

    return JSON.stringify({
      nombre: this.nuevoCentro.nombre.trim(),
      razonSocial: this.nuevoCentro.razonSocial.trim(),
      rut: this.normalizarRut(this.nuevoCentro.rut),
      direccion: this.nuevoCentro.direccion.trim(),
      telefono: this.nuevoCentro.telefono.trim(),
      esInterno: this.nuevoCentro.esInterno,
      areas: [...new Set(this.areasSeleccionadas().map((area) => area.trim()))].sort((a, b) => a.localeCompare(b, 'es')),
      cupos,
    });
  }

  private rutDuplicado(valor: string): boolean {
    const normalizado = this.normalizarRut(valor);
    return this.centros().some(
      (centro) =>
        centro.id !== this.centroEditandoId() && this.normalizarRut(centro.rut) === normalizado,
    );
  }

  private esRutChilenoValido(valor: string): boolean {
    const rut = this.normalizarRut(valor);
    if (!/^\d{7,8}[0-9K]$/.test(rut)) return false;
    const cuerpo = rut.slice(0, -1);
    const digitoIngresado = rut.slice(-1);
    let suma = 0;
    let factor = 2;
    for (let indice = cuerpo.length - 1; indice >= 0; indice--) {
      suma += Number(cuerpo[indice]) * factor;
      factor = factor === 7 ? 2 : factor + 1;
    }
    const resto = 11 - (suma % 11);
    const digitoEsperado = resto === 11 ? '0' : resto === 10 ? 'K' : String(resto);
    return digitoIngresado === digitoEsperado;
  }

  private normalizarRut(valor: string): string {
    return valor.replace(/[.\-\s]/g, '').toLocaleUpperCase('es');
  }

  private cargarCatalogo(): void {
    this.errorApi.set('');
    this.centrosService.listar().subscribe({
      next: (catalogo) => {
        this.centros.set(catalogo.centros);
        this.areasCatalogo.set(catalogo.areas.map((area) => area.nombre));
        this.periodosCatalogo.set(catalogo.periodos);
      },
      error: (error: unknown) => this.errorApi.set(this.centrosService.mensajeError(error)),
    });
  }

  private reemplazarCentro(actualizado: CentroPractica): void {
    this.centros.update((centros) =>
      centros.map((centro) => (centro.id === actualizado.id ? actualizado : centro)),
    );
  }
}
