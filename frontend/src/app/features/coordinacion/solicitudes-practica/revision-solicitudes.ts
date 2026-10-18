import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

import { Auth } from '../../../core/services/auth';
import { EstadoSolicitud, SolicitudPractica } from '../../solicitudes-practica/solicitud-practica.model';
import { SolicitudesPracticaService } from '../../solicitudes-practica/solicitudes-practica.service';

@Component({
  selector: 'app-revision-solicitudes',
  imports: [CommonModule, FormsModule, MatIconModule, RouterLink],
  templateUrl: './revision-solicitudes.html',
  styleUrl: './revision-solicitudes.scss',
})
export class RevisionSolicitudes {
  private readonly service = inject(SolicitudesPracticaService);
  private readonly auth = inject(Auth);
  protected readonly solicitudes = this.service.solicitudes;
  protected readonly solicitudId = signal<number | null>(null);
  protected readonly estadosActivos = signal<Extract<EstadoSolicitud, 'Por revisar' | 'Devuelta' | 'Aprobada'>[]>(['Por revisar']);
  protected readonly motivo = signal('');
  protected readonly error = signal('');
  protected readonly aviso = signal('');
  protected readonly filtradas = computed(() => this.solicitudes().filter((item) => this.estadosActivos().includes(item.estado as Extract<EstadoSolicitud, 'Por revisar' | 'Devuelta' | 'Aprobada'>)));
  protected readonly seleccionada = computed(() => this.filtradas().find((item) => item.id === this.solicitudId()) ?? this.filtradas()[0] ?? null);
  protected readonly pendientes = computed(() => this.solicitudes().filter((item) => item.estado === 'Por revisar'));
  protected readonly devueltas = computed(() => this.solicitudes().filter((item) => item.estado === 'Devuelta'));
  protected readonly aprobadas = computed(() => this.solicitudes().filter((item) => item.estado === 'Aprobada'));

  protected cambiarFiltro(estado: Extract<EstadoSolicitud, 'Por revisar' | 'Devuelta' | 'Aprobada'>): void {
    this.estadosActivos.update((actuales) => actuales.includes(estado)
      ? actuales.filter((item) => item !== estado)
      : [...actuales, estado]);
    this.solicitudId.set(null);
    this.motivo.set('');
    this.error.set('');
  }

  protected estadoCentro(solicitud: SolicitudPractica): string {
    if (solicitud.modoCentro === 'Propuesto') return 'Propuesto · pendiente de revisión';
    const centro = this.service.todosLosCentros.find((item) => item.id === solicitud.centroId);
    if (!centro) return 'No disponible en el catálogo';
    if (centro.esInterno) return 'Activo · Centro interno UTA';
    return `${centro.estado}${centro.acuerdo === 'Vigente' ? ' · acuerdo vigente' : ` · acuerdo ${centro.acuerdo.toLowerCase()}`}`;
  }

  protected direccionCentro(solicitud: SolicitudPractica): string {
    if (solicitud.modoCentro === 'Propuesto') return 'Pendiente de registro';
    return this.service.todosLosCentros.find((item) => item.id === solicitud.centroId)?.direccion || 'No informada';
  }

  protected seleccionar(id: number): void {
    this.solicitudId.set(id);
    this.motivo.set('');
    this.error.set('');
    this.aviso.set('');
  }

  protected aprobar(): void {
    const solicitud = this.seleccionada();
    if (!solicitud || solicitud.estado !== 'Por revisar') return;
    this.service.aprobar(solicitud.id, this.auth.usuario()?.name ?? 'Coordinadora');
    this.aviso.set(`Solicitud de ${solicitud.estudianteNombre} aprobada. Se registró la decisión en el historial.`);
    this.solicitudId.set(null);
    this.motivo.set('');
  }

  protected devolver(): void {
    const solicitud = this.seleccionada();
    if (!solicitud || solicitud.estado !== 'Por revisar') return;
    if (!this.motivo().trim()) {
      this.error.set('El motivo es obligatorio para devolver una solicitud.');
      return;
    }
    this.service.devolver(solicitud.id, this.motivo(), this.auth.usuario()?.name ?? 'Coordinadora');
    this.aviso.set(`Solicitud de ${solicitud.estudianteNombre} devuelta. El estudiante podrá editarla y reenviarla.`);
    this.solicitudId.set(null);
    this.motivo.set('');
    this.error.set('');
  }
}
