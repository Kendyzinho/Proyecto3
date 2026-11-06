import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';

import { AreaOpcion, CalendarioPractica, CentroAsignable, DIAS_SEMANA, EstudianteAsignable, HorarioPractica, ModalidadPractica } from './asignacion-practicas.model';
import { AsignacionPracticasService } from './asignacion-practicas.service';
import { FechasHorario } from './fechas-horario';

@Component({
  selector: 'app-asignacion-practicas',
  imports: [MatButtonModule, MatFormFieldModule, MatIconModule, MatSelectModule, MatTableModule],
  templateUrl: './asignacion-practicas.html',
  styleUrl: './asignacion-practicas.scss',
})
export class AsignacionPracticas {
  private readonly service = inject(AsignacionPracticasService);
  private readonly dialogo = inject(MatDialog);
  protected readonly columnasCalendario = ['estudiante', 'centro', 'fechas', 'horario', 'accion'];
  protected readonly calendarios = signal<CalendarioPractica[]>([]);
  protected readonly areasDisponibles = signal<AreaOpcion[]>([]);
  protected readonly idsAreas = signal<number[]>([]);
  protected readonly guardandoAreas = signal(false);
  protected readonly estudiantes = signal<EstudianteAsignable[]>([]);
  protected readonly centros = signal<CentroAsignable[]>([]);
  protected readonly estudianteId = signal<number | null>(null);
  protected readonly centroId = signal<number | null>(null);
  protected readonly supervisorId = signal<number | null>(null);
  protected readonly modalidad = signal<ModalidadPractica>('presencial');
  protected readonly cargandoEstudiantes = signal(false);
  protected readonly cargandoCentros = signal(false);
  protected readonly guardando = signal(false);
  protected readonly aviso = signal('');
  protected readonly avisoExitoso = signal(false);
  protected readonly estudiante = computed(() => this.estudiantes().find((item) => item.id === this.estudianteId()) ?? null);
  protected readonly centroSeleccionado = computed(() => this.centros().find((item) => item.id === this.centroId()) ?? null);
  protected readonly supervisorSeleccionado = computed(() => this.centroSeleccionado()?.supervisores.find((item) => item.id === this.supervisorId()) ?? null);
  protected readonly estudiantesSinCentro = computed(() => this.estudiantes());
  protected readonly centrosCompatibles = computed(() => this.centros().slice().sort((a, b) => this.contarCoincidencias(b) - this.contarCoincidencias(a)));

  constructor() {
    this.service.listarAreas().subscribe({
      next: (areas) => {
        this.areasDisponibles.set(areas);
        this.precargarAreas();
      },
      error: (error: unknown) => this.mostrarError(error, 'No se pudieron cargar las áreas.'),
    });
    this.cargarCalendarios();
    this.cargarHabilitaciones();
  }

  private cargarCalendarios(): void {
    this.service.listarCalendarios().subscribe({
      next: (calendarios) => this.calendarios.set(calendarios),
      error: (error: unknown) => this.mostrarError(error, 'No se pudieron cargar las fechas y horarios.'),
    });
  }

  private mostrarError(error: unknown, fallback: string): void {
    this.avisoExitoso.set(false);
    this.aviso.set(this.service.mensajeError(error, fallback));
  }

  private idsDe(nombres: string[]): number[] {
    return this.areasDisponibles().filter((area) => nombres.includes(area.nombre)).map((area) => area.id);
  }

  private precargarAreas(): void {
    this.idsAreas.set(this.idsDe(this.estudiante()?.areas ?? []));
  }

  // Sin áreas no hay centros compatibles: la Coordinadora las define al cerrar el selector
  protected guardarAreas(): void {
    const estudiante = this.estudiante();
    if (!estudiante) return;
    const ids = this.idsAreas();
    const actuales = this.idsDe(estudiante.areas);
    if (ids.length === actuales.length && ids.every((id) => actuales.includes(id))) return;
    if (!ids.length) {
      this.idsAreas.set(actuales);
      this.avisoExitoso.set(false);
      this.aviso.set('El estudiante debe tener al menos un área.');
      return;
    }
    this.guardandoAreas.set(true);
    this.aviso.set('');
    this.service.actualizarAreas(estudiante.id, ids).subscribe({
      next: (areas) => {
        this.estudiantes.update((items) => items.map((item) => (item.id === estudiante.id ? { ...item, areas } : item)));
        this.guardandoAreas.set(false);
        this.centroId.set(null);
        this.supervisorId.set(null);
        this.centros.set([]);
        this.cargarCentros(estudiante.id, estudiante.periodo);
      },
      error: (error: unknown) => {
        this.idsAreas.set(actuales);
        this.guardandoAreas.set(false);
        this.mostrarError(error, 'No se pudieron guardar las áreas.');
      },
    });
  }

  protected abrirFechasHorario(practica: CalendarioPractica): void {
    this.dialogo.open(FechasHorario, { data: practica, width: '560px', maxWidth: 'calc(100vw - 24px)', maxHeight: '94dvh' })
      .afterClosed().subscribe((guardado) => {
        if (guardado) this.cargarCalendarios();
      });
  }

  protected textoFechas(practica: CalendarioPractica): string {
    const formato = (fecha: string) => fecha.split('-').reverse().join('-');
    return practica.fecha_inicio && practica.fecha_fin ? `${formato(practica.fecha_inicio)} a ${formato(practica.fecha_fin)}` : '';
  }

  protected textoHorario(horario: HorarioPractica): string {
    const dias = horario.dias.map((dia) => DIAS_SEMANA.find((item) => item.valor === dia)?.etiqueta).join(', ');
    return `${dias}, ${horario.hora_desde} a ${horario.hora_hasta}`;
  }

  protected cargarHabilitaciones(): void {
    this.cargandoEstudiantes.set(true);
    this.estudiantes.set([]);
    this.estudianteId.set(null);
    this.centros.set([]);
    this.centroId.set(null);
    this.supervisorId.set(null);
    this.service.listarHabilitaciones().subscribe({
      next: (items) => {
        this.estudiantes.set(items);
        const primerEstudiante = items[0];
        if (primerEstudiante) this.abrirEstudiante(primerEstudiante.id);
        this.cargandoEstudiantes.set(false);
      },
      error: (error: unknown) => {
        this.avisoExitoso.set(false);
        this.aviso.set(this.service.mensajeError(error, 'No se pudieron cargar las habilitaciones.'));
        this.cargandoEstudiantes.set(false);
      },
    });
  }

  protected seleccionarEstudiante(id: number): void {
    this.aviso.set('');
    this.abrirEstudiante(id);
  }

  private abrirEstudiante(id: number): void {
    this.estudianteId.set(id);
    this.precargarAreas();
    this.centroId.set(null);
    this.supervisorId.set(null);
    this.centros.set([]);
    const periodo = this.estudiantes().find((item) => item.id === id)?.periodo ?? '';
    this.cargarCentros(id, periodo);
  }

  private cargarCentros(habilitacionId: number, periodo: string): void {
    this.cargandoCentros.set(true);
    this.service.listarCentros(habilitacionId, periodo).subscribe({
      next: (centros) => {
        this.centros.set(centros);
        this.cargandoCentros.set(false);
      },
      error: (error: unknown) => {
        this.avisoExitoso.set(false);
        this.aviso.set(this.service.mensajeError(error, 'No se pudieron cargar los centros compatibles.'));
        this.cargandoCentros.set(false);
      },
    });
  }

  protected seleccionarCentro(centroId: number): void {
    this.centroId.set(centroId);
    const centro = this.centros().find((item) => item.id === centroId);
    this.supervisorId.set(centro?.supervisores[0]?.id ?? null);
    this.aviso.set('');
  }

  protected seleccionarSupervisor(id: number): void {
    this.supervisorId.set(id);
    this.aviso.set('');
  }

  protected contarCoincidencias(centro: CentroAsignable): number {
    const areas = this.estudiante()?.areas ?? [];
    return centro.areas.filter((area) => areas.includes(area)).length;
  }

  protected textoCoincidencia(centro: CentroAsignable, total: number): string {
    return `coincide en ${this.contarCoincidencias(centro)} de ${total} ${total === 1 ? 'área' : 'áreas'}`;
  }

  protected iniciales(nombre: string): string {
    return nombre.split(/\s+/).filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toLocaleUpperCase('es');
  }

  protected asignar(): void {
    const estudiante = this.estudiante();
    const centro = this.centroSeleccionado();
    const supervisor = this.supervisorSeleccionado();
    if (!estudiante || !centro || !supervisor) {
      this.avisoExitoso.set(false);
      this.aviso.set('Selecciona un centro y un supervisor activo para continuar.');
      return;
    }

    this.guardando.set(true);
    this.aviso.set('');
    this.service.crearAsignacion({
      habilitacion_id: estudiante.id,
      centro_id: centro.id,
      supervisor_id: supervisor.id,
      modalidad: this.modalidad(),
    }).subscribe({
      next: ({ message }) => {
        this.avisoExitoso.set(true);
        this.aviso.set(`${message} Se avisará al estudiante por correo.`);
        this.guardando.set(false);
        this.cargarHabilitaciones();
        this.cargarCalendarios();
      },
      error: (error: unknown) => {
        this.avisoExitoso.set(false);
        this.aviso.set(this.service.mensajeError(error, 'No se pudo registrar la asignación.'));
        this.guardando.set(false);
      },
    });
  }
}
