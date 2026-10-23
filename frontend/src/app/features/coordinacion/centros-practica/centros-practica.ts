import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

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

type FiltroAcuerdo = 'Todos' | EstadoAcuerdo;
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
  imports: [FormsModule],
  templateUrl: './centros-practica.html',
  styleUrl: './centros-practica.scss',
})
export class CentrosPractica {
  private readonly centrosService = inject(CentrosPracticaService);
  private readonly centros = signal<CentroPractica[]>([]);

  protected readonly errorApi = signal('');
  protected readonly guardando = signal(false);
  protected readonly areasCatalogo = signal<string[]>([]);
  protected readonly periodosCatalogo = signal<{ id: number; anio: number; semestre: number }[]>([]);

  protected readonly busqueda = signal('');
  protected readonly filtroAcuerdo = signal<FiltroAcuerdo>('Todos');
  protected readonly areaSeleccionada = signal('Todas');
  protected readonly mostrarDadosDeBaja = signal(false);
  protected readonly centroSeleccionadoId = signal<number | null>(null);
  protected readonly mostrarFormularioNuevo = signal(false);
  protected readonly centroEditandoId = signal<number | null>(null);
  protected readonly areasSeleccionadas = signal<string[]>([]);
  protected readonly nuevaArea = signal('');
  protected readonly intentoGuardar = signal(false);
  protected readonly mostrarFormularioBaja = signal(false);
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
  protected periodoNuevoCupo = '2° semestre 2026';
  protected categoriaNuevoCupo: 'Laboral' | 'Profesional' = 'Laboral';
  protected cantidadNuevoCupo = 1;

  protected nuevoCentro = this.formularioVacio();

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
      const coincideAcuerdo =
        this.filtroAcuerdo() === 'Todos' || centro.acuerdo === this.filtroAcuerdo();
      const coincideArea =
        this.areaSeleccionada() === 'Todas' || centro.areas.includes(this.areaSeleccionada());
      const coincideEstado = this.mostrarDadosDeBaja() || centro.estado !== 'Dado de baja';
      return coincideTexto && coincideAcuerdo && coincideArea && coincideEstado;
    });
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
  }

  protected reintentarCarga(): void {
    this.cargarCatalogo();
  }

  protected seleccionarFiltroAcuerdo(valor: FiltroAcuerdo): void {
    this.filtroAcuerdo.set(valor);
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
    this.mostrarFormularioNuevo.set(true);
  }

  protected editarCentro(): void {
    const centro = this.centroSeleccionado();
    if (!centro) return;
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
    this.mostrarFormularioNuevo.set(true);
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
    const cantidad = centro.supervisores.length;
    return cantidad === 0 ? 'Sin supervisores' : `${cantidad} ${cantidad === 1 ? 'supervisor(a)' : 'supervisores'}`;
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
    this.mostrarFormularioNuevo.set(false);
    this.limpiarFormulario();
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
    const periodo = this.periodoNuevoCupo.trim();
    if (!periodo || !Number.isInteger(this.cantidadNuevoCupo) || this.cantidadNuevoCupo < 1) return;
    const coincide = periodo.match(/([12])\D*sem(?:estre)?\D*(\d{4})/i);
    const periodoRegistrado = coincide
      ? this.periodosCatalogo().find(
          (item) => item.semestre === Number(coincide[1]) && item.anio === Number(coincide[2]),
        )
      : undefined;
    if (!periodoRegistrado) {
      this.errorApi.set('El período debe existir en el sistema antes de asignarle cupos.');
      return;
    }
    this.errorApi.set('');
    const cupo: CupoCentro = {
      periodo,
      periodoId: periodoRegistrado.id,
      categoria: this.categoriaNuevoCupo,
      total: this.cantidadNuevoCupo,
      ocupados: 0,
      libres: this.cantidadNuevoCupo,
    };
    this.cuposFormulario.update((cupos) => [
      ...cupos.filter((item) => item.periodo !== cupo.periodo || item.categoria !== cupo.categoria),
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
    const actual = this.centros().find((centro) => centro.id === centroEditando);
    const datos: CentroPractica = {
      id: centroEditando ?? Math.max(0, ...this.centros().map((item) => item.id)) + 1,
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
        this.filtroAcuerdo.set('Todos');
        this.mostrarDadosDeBaja.set(false);
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
    this.mostrarFormularioBaja.set(true);
  }

  protected cerrarBaja(): void {
    this.mostrarFormularioBaja.set(false);
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
  }

  protected cerrarFichaEstudiante(): void {
    this.estudianteSeleccionado.set(null);
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

  protected cuposResumen(centro: CentroPractica): string {
    if (!centro.cupos.length) return 'Sin cupos definidos';
    return centro.cupos
      .map((cupo) => `${cupo.periodo}: ${cupo.categoria} ${cupo.libres}/${cupo.total}`)
      .join(' · ');
  }

  protected estadoCentroClass(estado: EstadoCentro): string {
    return estado === 'Activo'
      ? 'status--active'
      : estado === 'Propuesto'
        ? 'status--proposed'
        : estado === 'Sin acuerdo'
          ? 'status--neutral'
          : 'status--inactive';
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
      ? 'status--active'
      : estado === 'En trámite'
        ? 'status--proposed'
        : estado === 'Vencido'
          ? 'status--inactive'
          : 'status--neutral';
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
    this.nuevoCentro = this.formularioVacio();
    this.areasSeleccionadas.set([]);
    this.nuevaArea.set('');
    this.cuposFormulario.set([]);
    this.supervisoresFormulario.set([]);
    this.intentoGuardar.set(false);
    this.periodoNuevoCupo = '2° semestre 2026';
    this.categoriaNuevoCupo = 'Laboral';
    this.cantidadNuevoCupo = 1;
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
