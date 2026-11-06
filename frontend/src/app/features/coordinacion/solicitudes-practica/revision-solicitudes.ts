import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSnackBar } from '@angular/material/snack-bar';

import { ContadorSolicitudes } from '../../../shared/marco/contador-solicitudes';
import { EstadoSolicitud, SolicitudPractica } from '../../solicitudes-practica/solicitud-practica.model';
import { SolicitudesPracticaService } from '../../solicitudes-practica/solicitudes-practica.service';
import { NuevaPractica } from '../nueva-practica';
import { PracticasApi } from '../practicas-api';

type EstadoBandeja = Extract<EstadoSolicitud, 'Por revisar' | 'Devuelta' | 'Aprobada'>;

const DIAS_LABORALES = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];

// HU-09: revisión de solicitudes. "Nueva práctica" abre el registro excepcional de la HU-11
@Component({
  selector: 'app-revision-solicitudes',
  imports: [CommonModule, FormsModule, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './revision-solicitudes.html',
  styleUrl: './revision-solicitudes.scss',
})
export class RevisionSolicitudes {
  protected readonly service = inject(SolicitudesPracticaService);
  private readonly practicas = inject(PracticasApi);
  private readonly dialogo = inject(MatDialog);
  private readonly mensajes = inject(MatSnackBar);
  private readonly contador = inject(ContadorSolicitudes);
  protected readonly solicitudes = this.service.solicitudes;
  protected readonly solicitudId = signal<number | null>(null);
  protected readonly estadosActivos = signal<EstadoBandeja[]>(['Por revisar']);
  protected readonly motivo = signal('');
  protected readonly error = signal('');
  protected readonly aviso = signal('');
  protected readonly guardando = signal(false);
  protected readonly filtradas = computed(() => this.solicitudes().filter((item) => this.estadosActivos().includes(item.estado as EstadoBandeja)));
  protected readonly seleccionada = computed(() => this.filtradas().find((item) => item.id === this.solicitudId()) ?? this.filtradas()[0] ?? null);
  protected readonly filtros = computed(() => ([
    { estado: 'Por revisar', texto: 'Por revisar' },
    { estado: 'Devuelta', texto: 'Devueltas' },
    { estado: 'Aprobada', texto: 'Aprobadas' },
  ] as { estado: EstadoBandeja; texto: string }[]).map((filtro) => ({
    ...filtro,
    cantidad: this.solicitudes().filter((item) => item.estado === filtro.estado).length,
  })));

  constructor() {
    this.service.cargarBandeja().subscribe({ error: () => {} });
  }

  protected cambiarFiltro(estado: EstadoBandeja): void {
    this.estadosActivos.update((actuales) => actuales.includes(estado)
      ? actuales.filter((item) => item !== estado)
      : [...actuales, estado]);
    this.seleccionar(null);
  }

  protected seleccionar(id: number | null): void {
    this.solicitudId.set(id);
    this.motivo.set('');
    this.error.set('');
    this.aviso.set('');
  }

  protected nuevaPractica(): void {
    this.practicas.metricas().subscribe(({ periodo }) => {
      if (!periodo) {
        this.aviso.set('No hay un período académico disponible para registrar prácticas.');
        return;
      }
      this.dialogo.open(NuevaPractica, { data: periodo, width: '640px', maxWidth: 'calc(100vw - 24px)', maxHeight: '94dvh' })
        .afterClosed().subscribe((creada: boolean | undefined) => {
          if (creada) this.mensajes.open('Práctica registrada.', 'Cerrar', { duration: 6000 });
        });
    });
  }

  protected iniciales(nombre: string): string {
    return nombre.split(/\s+/).filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toLocaleUpperCase('es');
  }

  protected claseEstado(estado: EstadoSolicitud): string {
    return estado === 'Aprobada' ? 'estado--exito' : estado === 'Devuelta' ? 'estado--advertencia' : 'estado--info';
  }

  private centro(solicitud: SolicitudPractica) {
    return this.service.todosLosCentros().find((item) => item.id === solicitud.centroId) ?? null;
  }

  protected nombreCentro(solicitud: SolicitudPractica): string {
    return this.centro(solicitud)?.nombre ?? solicitud.centroRazonSocial;
  }

  protected direccionCentro(solicitud: SolicitudPractica): string {
    return this.centro(solicitud)?.direccion || 'Sin registrar';
  }

  protected estadoCentro(solicitud: SolicitudPractica): string {
    const centro = this.centro(solicitud);
    if (!centro) return 'No disponible en el catálogo';
    if (centro.estado === 'Propuesto') return 'Propuesto';
    if (centro.esInterno) return 'Centro interno';
    return centro.acuerdo === 'Vigente' ? 'Registrado con acuerdo vigente' : `Acuerdo ${centro.acuerdo.toLowerCase()}`;
  }

  protected claseEstadoCentro(solicitud: SolicitudPractica): string {
    const centro = this.centro(solicitud);
    if (centro?.estado === 'Propuesto') return 'estado--info';
    return centro?.esInterno || centro?.acuerdo === 'Vigente' ? 'estado--exito' : 'estado--advertencia';
  }

  protected cargoYUnidad(solicitud: SolicitudPractica): string {
    const { supervisorCargo: cargo, supervisorUnidad: unidad } = solicitud;
    if (cargo && unidad) return `${cargo}, unidad de ${unidad}`;
    return cargo || unidad || 'Sin informar';
  }

  protected horario(solicitud: SolicitudPractica): string {
    const { dias, horaDesde, horaHasta } = solicitud.horario;
    const deLunesAViernes = dias.length === 5 && DIAS_LABORALES.every((dia) => dias.includes(dia));
    return `${deLunesAViernes ? 'Lunes a viernes' : dias.join(', ')}, ${horaDesde} a ${horaHasta}`;
  }

  protected aprobar(): void {
    const solicitud = this.seleccionada();
    if (!solicitud || solicitud.estado !== 'Por revisar') return;
    this.guardando.set(true);
    this.error.set('');
    this.service.aprobar(solicitud.id).subscribe({
      next: () => this.terminarDecision(`Solicitud de ${solicitud.estudianteNombre} aprobada.`),
      error: (error: unknown) => {
        this.error.set(this.service.mensajeError(error));
        this.guardando.set(false);
      },
    });
  }

  protected devolver(): void {
    const solicitud = this.seleccionada();
    if (!solicitud || solicitud.estado !== 'Por revisar') return;
    if (!this.motivo().trim()) {
      this.error.set('Escribe el motivo para devolver la solicitud.');
      return;
    }
    this.guardando.set(true);
    this.error.set('');
    this.service.devolver(solicitud.id, this.motivo().trim()).subscribe({
      next: () => this.terminarDecision(`Solicitud de ${solicitud.estudianteNombre} devuelta.`),
      error: (error: unknown) => {
        this.error.set(this.service.mensajeError(error));
        this.guardando.set(false);
      },
    });
  }

  private terminarDecision(mensaje: string): void {
    this.seleccionar(null);
    this.aviso.set(mensaje);
    this.guardando.set(false);
    this.contador.actualizar();
  }
}
