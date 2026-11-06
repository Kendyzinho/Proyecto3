import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { AreaOpcion, CalendarioPractica, CentroAsignable, EstudianteAsignable, HorarioPractica, ModalidadPractica } from './asignacion-practicas.model';

interface Respuesta<T> {
  data: T;
  message?: string;
}

interface HabilitacionApi {
  id: number;
  estudiante_id: number;
  estudiante: string;
  email: string;
  categoria: 'laboral';
  periodo_id: number;
  tipo_practica: string;
  anio: number;
  semestre: number;
  areas: string[];
}

interface CentroApi {
  id: number;
  nombre: string;
  razon_social: string;
  rut: string | null;
  direccion: string | null;
  es_interno: boolean;
  cupos: number;
  ocupados: number;
  periodo_id: number;
  categoria: 'laboral';
  areas_coincidentes: string[];
  supervisores: { id: number; nombre: string; correo: string }[];
}

@Injectable({ providedIn: 'root' })
export class AsignacionPracticasService {
  private readonly http = inject(HttpClient);

  listarHabilitaciones(): Observable<EstudianteAsignable[]> {
    return this.http.get<Respuesta<HabilitacionApi[]>>('/api/coordinacion/asignaciones/habilitaciones')
      .pipe(map(({ data }) => data.map((item) => ({
      id: item.id,
      nombre: item.estudiante,
      carrera: 'Diseño Multimedia',
      categoria: 'Laboral',
      periodo: `${item.semestre}º semestre ${item.anio}`,
      periodoId: item.periodo_id,
      areas: item.areas,
      habilitado: true,
      practicaAsignada: false,
    }))));
  }

  listarCentros(habilitacionId: number, periodo: string): Observable<CentroAsignable[]> {
    return this.http.get<Respuesta<CentroApi[]>>(`/api/coordinacion/asignaciones/habilitaciones/${habilitacionId}/centros`)
      .pipe(map(({ data }) => data.map((item) => ({
        id: item.id,
        nombre: item.nombre,
        rut: item.rut ?? 'Sin RUT',
        direccion: item.direccion ?? '',
        estado: 'Activo' as const,
        acuerdoVigente: true,
        areas: item.areas_coincidentes,
        cupos: [{ periodo, categoria: 'Laboral', total: item.cupos, ocupados: item.ocupados }],
        supervisores: item.supervisores,
      }))));
  }

  crearAsignacion(payload: {
    habilitacion_id: number;
    centro_id: number;
    supervisor_id: number;
    modalidad: ModalidadPractica;
  }): Observable<{ message: string }> {
    return this.http.post<{ message: string }>('/api/coordinacion/asignaciones', payload);
  }

  listarAreas(): Observable<AreaOpcion[]> {
    return this.http.get<Respuesta<AreaOpcion[]>>('/api/areas').pipe(map(({ data }) => data));
  }

  actualizarAreas(habilitacionId: number, areaIds: number[]): Observable<string[]> {
    return this.http.put<Respuesta<{ areas: string[] }>>(`/api/coordinacion/asignaciones/habilitaciones/${habilitacionId}/areas`, { area_ids: areaIds })
      .pipe(map(({ data }) => data.areas));
  }

  listarCalendarios(): Observable<CalendarioPractica[]> {
    return this.http.get<Respuesta<CalendarioPractica[]>>('/api/coordinacion/calendarios').pipe(map(({ data }) => data));
  }

  guardarCalendario(practicaId: number, datos: { fecha_inicio: string; fecha_fin: string } & HorarioPractica): Observable<{ message: string }> {
    return this.http.put<{ message: string }>(`/api/coordinacion/calendarios/${practicaId}`, datos);
  }

  mensajeError(error: unknown, fallback: string): string {
    if (error instanceof HttpErrorResponse) {
      const payload = error.error as { message?: string; errors?: Record<string, string[]> } | null;
      return payload?.errors ? Object.values(payload.errors).flat()[0] ?? fallback : payload?.message ?? fallback;
    }
    return fallback;
  }
}
