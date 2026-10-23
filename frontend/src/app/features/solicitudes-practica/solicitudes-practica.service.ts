import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, forkJoin, map, tap } from 'rxjs';

import { CentroPractica } from '../coordinacion/centros-practica/centro-practica.model';
import { HistorialDecision, SolicitudFormulario, SolicitudPractica } from './solicitud-practica.model';

interface ApiResponse<T> { data: T }
interface CentroApi {
  id: number; nombre: string; razon_social: string; rut: string | null; direccion: string | null;
  telefono: string | null; es_interno: boolean; estado: string; estado_acuerdo: string;
  supervisores: Array<{ id: number; nombre: string; correo: string; cargo: string | null; unidad: string | null; telefono: string | null; fecha_alta: string | null; fecha_baja: string | null }>;
}
interface EstadoEstudianteApi {
  habilitacion: { id: number | null; puede_postular: boolean; motivo: string; periodo?: string; cumple_prerrequisito?: boolean };
  solicitud: SolicitudPractica | null;
  borrador?: SolicitudFormulario | null;
}

const ESTADO_VACIO = { puedePostular: false, motivo: 'Consultando tu habilitación de práctica profesional…' };

@Injectable({ providedIn: 'root' })
export class SolicitudesPracticaService {
  private readonly http = inject(HttpClient);

  readonly habilitacion = signal(ESTADO_VACIO);
  readonly cargando = signal(false);
  readonly errorApi = signal('');
  readonly centros = signal<CentroPractica[]>([]);
  readonly todosLosCentros = this.centros;
  readonly solicitudes = signal<SolicitudPractica[]>([]);
  readonly historial = signal<HistorialDecision[]>([]);
  readonly solicitudActual = signal<SolicitudPractica | null>(null);
  readonly borradorActual = signal<SolicitudFormulario | null>(null);

  cargarEstudiante(): Observable<void> {
    this.cargando.set(true);
    this.errorApi.set('');
    return forkJoin({
      estado: this.http.get<ApiResponse<EstadoEstudianteApi>>('/api/practicas/solicitud-profesional'),
      centros: this.http.get<ApiResponse<CentroApi[]>>('/api/centros-practica'),
    }).pipe(
      tap(({ estado, centros }) => {
        this.habilitacion.set({ puedePostular: estado.data.habilitacion.puede_postular, motivo: estado.data.habilitacion.motivo });
        this.solicitudActual.set(estado.data.solicitud);
        this.borradorActual.set(estado.data.solicitud ? null : estado.data.borrador ?? null);
        this.solicitudes.set(estado.data.solicitud ? [estado.data.solicitud] : []);
        this.centros.set(centros.data.map((centro) => this.mapearCentro(centro)));
      }),
      map(() => undefined),
      tap({ complete: () => this.cargando.set(false), error: (error) => {
        this.cargando.set(false);
        this.errorApi.set(this.mensajeError(error));
      } }),
    );
  }

  cargarBandeja(): Observable<void> {
    this.cargando.set(true);
    this.errorApi.set('');
    return forkJoin({
      solicitudes: this.http.get<ApiResponse<SolicitudPractica[]>>('/api/solicitudes-practica'),
      centros: this.http.get<ApiResponse<CentroApi[]>>('/api/centros-practica'),
    }).pipe(
      tap(({ solicitudes, centros }) => {
        this.solicitudes.set(solicitudes.data);
        this.centros.set(centros.data.map((centro) => this.mapearCentro(centro)));
      }),
      map(() => undefined),
      tap({ complete: () => this.cargando.set(false), error: (error) => {
        this.cargando.set(false);
        this.errorApi.set(this.mensajeError(error));
      } }),
    );
  }

  guardarBorrador(formulario: SolicitudFormulario): Observable<void> {
    return this.http.post<ApiResponse<{ guardado: boolean }>>('/api/practicas/solicitud-profesional', {
      accion: 'borrador', datos: formulario,
    }).pipe(map(() => undefined), tap(() => this.borradorActual.set(structuredClone(formulario))));
  }

  enviar(formulario: SolicitudFormulario): Observable<SolicitudPractica> {
    return this.http.post<ApiResponse<SolicitudPractica>>('/api/practicas/solicitud-profesional', this.payload(formulario)).pipe(
      map(({ data }) => data),
      tap((solicitud) => {
        this.solicitudActual.set(solicitud);
        this.solicitudes.set([solicitud]);
        this.borradorActual.set(null);
      }),
    );
  }

  aprobar(id: number): Observable<SolicitudPractica> {
    return this.decidir(id, 'aprobar').pipe(tap((solicitud) => this.actualizarBandeja(solicitud)));
  }

  devolver(id: number, motivo: string): Observable<SolicitudPractica> {
    return this.decidir(id, 'devolver', motivo).pipe(tap((solicitud) => this.actualizarBandeja(solicitud)));
  }

  mensajeError(error: unknown): string {
    const response = error as { error?: { message?: string; errors?: Record<string, string[]> }; message?: string };
    return Object.values(response?.error?.errors ?? {})[0]?.[0] ?? response?.error?.message ?? response?.message ?? 'No se pudo conectar con el backend.';
  }

  private decidir(id: number, decision: 'aprobar' | 'devolver', motivo?: string): Observable<SolicitudPractica> {
    return this.http.post<ApiResponse<SolicitudPractica>>(`/api/practicas/${id}/decision`, { decision, motivo }).pipe(map(({ data }) => data));
  }

  private payload(formulario: SolicitudFormulario): Record<string, unknown> {
    return {
      accion: 'enviar',
      centro_propuesto: formulario.modoCentro === 'Propuesto',
      centro_id: formulario.centroId,
      centro_razon_social: formulario.centroRazonSocial,
      centro_rut: formulario.centroRut,
      supervisor_propuesto: formulario.modoSupervisor === 'Propuesto',
      supervisor_correo: formulario.supervisorId,
      supervisor: {
        nombre: formulario.supervisorNombre,
        correo: formulario.supervisorCorreo,
        cargo: formulario.supervisorCargo,
        telefono: formulario.supervisorTelefono,
        unidad: formulario.supervisorUnidad,
      },
      modalidad: formulario.horario.modalidad,
      fecha_inicio: formulario.horario.fechaInicio,
      fecha_termino: formulario.horario.fechaTermino,
      tareas: formulario.tareas,
      horario: {
        dias: formulario.horario.dias,
        hora_desde: formulario.horario.horaDesde,
        hora_hasta: formulario.horario.horaHasta,
      },
    };
  }

  private actualizarBandeja(actualizada: SolicitudPractica): void {
    this.solicitudes.update((items) => items.map((item) => item.id === actualizada.id ? actualizada : item));
  }

  private mapearCentro(centro: CentroApi): CentroPractica {
    const acuerdo = centro.estado_acuerdo === 'vigente' ? 'Vigente'
      : centro.estado_acuerdo === 'en_tramite' ? 'En trámite'
        : centro.estado_acuerdo === 'vencido' ? 'Vencido' : 'Sin acuerdo';
    return {
      id: centro.id, nombre: centro.nombre, razonSocial: centro.razon_social, rut: centro.rut ?? '',
      areas: [], acuerdo, cupos: [], supervisorPrincipal: centro.supervisores[0]?.nombre ?? null,
      estado: centro.estado === 'propuesto' ? 'Propuesto' : centro.estado === 'sin_acuerdo' ? 'Sin acuerdo' : centro.estado === 'dado_de_baja' ? 'Dado de baja' : 'Activo',
      direccion: centro.direccion ?? '', telefono: centro.telefono ?? '', esInterno: centro.es_interno,
      representanteLegal: null, tipoAcuerdo: null, vigenciaAcuerdo: null,
      supervisores: centro.supervisores.map((supervisor) => ({
        id: supervisor.id, nombre: supervisor.nombre, correo: supervisor.correo,
        cargo: supervisor.cargo ?? '', unidad: supervisor.unidad ?? '', telefono: supervisor.telefono ?? '',
        estado: supervisor.fecha_baja ? 'De baja' : supervisor.fecha_alta ? 'Activo' : 'Propuesto',
      })),
      estudiantes: [],
    };
  }
}
