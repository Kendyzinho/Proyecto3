import { Component, computed, inject, signal } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';

import { CentroAsignable } from './asignacion-practicas.model';
import { AsignacionPracticasService } from './asignacion-practicas.service';

type CategoriaPractica = 'Laboral' | 'Profesional';

@Component({
  selector: 'app-asignacion-practicas',
  imports: [MatIconModule],
  templateUrl: './asignacion-practicas.html',
  styleUrl: './asignacion-practicas.scss',
})
export class AsignacionPracticas {
  private readonly service = inject(AsignacionPracticasService);
  protected readonly estudiantes = this.service.estudiantes;
  protected readonly centros = this.service.centros;
  protected readonly categoriaActiva = signal<CategoriaPractica>('Laboral');
  protected readonly estudianteId = signal<number | null>(101);
  protected readonly centroId = signal<number | null>(201);
  protected readonly aviso = signal('');
  protected readonly avisoExitoso = signal(false);
  protected readonly estudiante = computed(() => this.estudiantes().find((item) => item.id === this.estudianteId()) ?? null);
  protected readonly estudiantesSinCentro = computed(() => this.estudiantes().filter((item) => item.categoria === this.categoriaActiva() && item.habilitado && !item.practicaAsignada));
  protected readonly cantidadLaborales = computed(() => this.estudiantes().filter((item) => item.categoria === 'Laboral' && item.habilitado && !item.practicaAsignada).length);
  protected readonly cantidadProfesionales = computed(() => this.estudiantes().filter((item) => item.categoria === 'Profesional' && item.habilitado && !item.practicaAsignada).length);
  protected readonly centrosCompatibles = computed(() => {
    const estudiante = this.estudiante();
    if (!estudiante) return [];
    return this.centros()
      .filter((centro) => {
        const cupo = centro.cupos.find((item) => item.periodo === estudiante.periodo && item.categoria === estudiante.categoria);
        return centro.estado === 'Activo' && centro.acuerdoVigente &&
          this.contarCoincidencias(centro) > 0 && cupo !== undefined && cupo.ocupados < cupo.total;
      })
      .sort((a, b) => this.contarCoincidencias(b) - this.contarCoincidencias(a));
  });

  protected contarCoincidencias(centro: CentroAsignable): number {
    const areas = this.estudiante()?.areas ?? [];
    return centro.areas.filter((area) => areas.includes(area)).length;
  }

  protected seleccionarEstudiante(id: number): void {
    this.estudianteId.set(id);
    this.centroId.set(this.centrosCompatibles()[0]?.id ?? null);
    this.aviso.set('');
  }

  protected cambiarCategoria(categoria: CategoriaPractica): void {
    this.categoriaActiva.set(categoria);
    const primerEstudiante = this.estudiantesSinCentro()[0];
    this.estudianteId.set(primerEstudiante?.id ?? null);
    this.centroId.set(this.centrosCompatibles()[0]?.id ?? null);
    this.aviso.set('');
  }

  protected iniciales(nombre: string): string {
    return nombre.split(/\s+/).filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toLocaleUpperCase('es');
  }

  protected asignar(): void {
    const estudianteId = this.estudianteId();
    const centroId = this.centroId();
    if (estudianteId === null || centroId === null) {
      this.avisoExitoso.set(false);
      this.aviso.set('Selecciona un centro para confirmar la asignación.');
      return;
    }
    const estudianteNombre = this.estudiante()?.nombre;
    const centroNombre = this.centros().find((centro) => centro.id === centroId)?.nombre;
    const error = this.service.crearAsignacion(estudianteId, centroId);
    if (error) {
      this.avisoExitoso.set(false);
      this.aviso.set(error);
      return;
    }
    this.avisoExitoso.set(true);
    this.aviso.set(`Asignación registrada para ${estudianteNombre} en ${centroNombre}. El correo se enviará cuando se conecte la API.`);
    const siguienteEstudiante = this.estudiantesSinCentro()[0];
    this.estudianteId.set(siguienteEstudiante?.id ?? null);
    this.centroId.set(this.centrosCompatibles()[0]?.id ?? null);
  }
}
