import { Component, computed, inject, signal } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';

import { CentroAsignable, EstudianteAsignable, ModalidadPractica } from './asignacion-practicas.model';
import { AsignacionPracticasService } from './asignacion-practicas.service';

@Component({
  selector: 'app-asignacion-practicas',
  imports: [MatIconModule],
  templateUrl: './asignacion-practicas.html',
  styleUrl: './asignacion-practicas.scss',
})
export class AsignacionPracticas {
  private readonly service = inject(AsignacionPracticasService);
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
    this.cargarHabilitaciones();
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
        if (primerEstudiante) this.seleccionarEstudiante(primerEstudiante.id);
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
    this.estudianteId.set(id);
    this.centroId.set(null);
    this.supervisorId.set(null);
    this.centros.set([]);
    this.aviso.set('');
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

  protected cambiarModalidad(event: Event): void {
    this.modalidad.set((event.target as HTMLSelectElement).value as ModalidadPractica);
  }

  protected contarCoincidencias(centro: CentroAsignable): number {
    const areas = this.estudiante()?.areas ?? [];
    return centro.areas.filter((area) => areas.includes(area)).length;
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
        this.aviso.set(`${message} Se encoló la notificación por correo.`);
        this.guardando.set(false);
        this.cargarHabilitaciones();
      },
      error: (error: unknown) => {
        this.avisoExitoso.set(false);
        this.aviso.set(this.service.mensajeError(error, 'No se pudo registrar la asignación.'));
        this.guardando.set(false);
      },
    });
  }
}
