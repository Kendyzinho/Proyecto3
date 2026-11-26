import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { RespuestaGuardada, RespuestaItem } from '../../../core/models/instrumento';

export type EstadoInforme = 'sin_solicitar' | 'solicitado' | 'borrador' | 'enviado';

export interface PracticaInforme {
  id: number;
  estudiante: string;
  tipo_practica: string;
  centro: string | null;
  supervisor: string | null;
  fecha_fin: string | null;
}

export interface FilaInforme extends PracticaInforme {
  estado: EstadoInforme;
  solicitado_en: string | null;
  enviado_en: string | null;
}

export interface Informe {
  practica: PracticaInforme;
  informe: RespuestaGuardada;
}

export const ESTADOS_INFORME: Record<EstadoInforme, { texto: string; clase: string }> = {
  sin_solicitar: { texto: 'Sin solicitar', clase: 'estado--neutro' },
  solicitado: { texto: 'Solicitado', clase: 'estado--info' },
  borrador: { texto: 'En borrador', clase: 'estado--advertencia' },
  enviado: { texto: 'Enviado', clase: 'estado--exito' },
};

// Secciones del formulario oficial, en el orden en que se responden
export const SECCIONES_INFORME: { clave: string; titulo: string }[] = [
  { clave: 'transversal', titulo: 'Habilidades transversales' },
  { clave: 'disciplinar', titulo: 'Habilidades disciplinares' },
  { clave: 'cierre', titulo: 'Comentario' },
];

// HU-19: la Coordinadora o el Jefe piden el informe y lo leen cuando está enviado
@Injectable({ providedIn: 'root' })
export class InformesApi {
  private readonly http = inject(HttpClient);

  listar(): Observable<FilaInforme[]> {
    return this.http.get<{ data: FilaInforme[] }>('/api/coordinacion/informes').pipe(map((r) => r.data));
  }

  solicitar(practica: number): Observable<{ data: FilaInforme; message: string }> {
    return this.http.post<{ data: FilaInforme; message: string }>(`/api/coordinacion/informes/${practica}/solicitar`, {});
  }

  ver(practica: number): Observable<Informe> {
    return this.http.get<{ data: Informe }>(`/api/coordinacion/informes/${practica}`).pipe(map((r) => r.data));
  }
}

// HU-19: el supervisor(a) responde los informes de sus estudiantes
@Injectable({ providedIn: 'root' })
export class InformesSupervisorApi {
  private readonly http = inject(HttpClient);

  listar(): Observable<FilaInforme[]> {
    return this.http.get<{ data: FilaInforme[] }>('/api/supervisor/informes').pipe(map((r) => r.data));
  }

  ver(practica: number): Observable<Informe> {
    return this.http.get<{ data: Informe }>(`/api/supervisor/informes/${practica}`).pipe(map((r) => r.data));
  }

  guardar(practica: number, respuestas: RespuestaItem[], enviar: boolean): Observable<Informe> {
    return this.http.put<{ data: Informe }>(`/api/supervisor/informes/${practica}`, { respuestas, enviar }).pipe(map((r) => r.data));
  }
}
