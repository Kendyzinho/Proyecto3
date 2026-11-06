import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

export type Categoria = 'laboral' | 'profesional';
export type EstadoMalla = 'vigente' | 'programada' | 'en_preparacion';

// Una práctica de la malla tal como la entrega la API
export interface PracticaMalla {
  id: number;
  malla_id: number;
  nombre: string;
  categoria: Categoria;
  horas_requeridas: number;
  prerrequisito: string | null;
}

export interface Malla {
  id: number;
  nombre: string;
  vigente_desde: string | null;
  estado: EstadoMalla;
  practicas: PracticaMalla[];
}

export interface DatosPractica {
  nombre: string;
  categoria: Categoria;
  horas_requeridas: number;
  prerrequisito: string | null;
}

export const CATEGORIAS: { valor: Categoria; texto: string }[] = [
  { valor: 'laboral', texto: 'Laboral' },
  { valor: 'profesional', texto: 'Profesional' },
];

// HU-06: mallas y sus prácticas (solo Jefe de Carrera)
@Injectable({ providedIn: 'root' })
export class MallasApi {
  private readonly http = inject(HttpClient);

  listar(): Observable<Malla[]> {
    return this.http.get<{ data: Malla[] }>('/api/mallas').pipe(map(({ data }) => data));
  }

  crear(nombre: string): Observable<Malla> {
    return this.http.post<{ data: Malla }>('/api/mallas', { nombre }).pipe(map(({ data }) => data));
  }

  publicar(id: number, vigenteDesde: string): Observable<Malla> {
    return this.http
      .post<{ data: Malla }>(`/api/mallas/${id}/publicar`, { vigente_desde: vigenteDesde })
      .pipe(map(({ data }) => data));
  }

  agregarPractica(mallaId: number, datos: DatosPractica): Observable<PracticaMalla> {
    return this.http.post<{ data: PracticaMalla }>(`/api/mallas/${mallaId}/practicas`, datos).pipe(map(({ data }) => data));
  }

  editarPractica(id: number, datos: DatosPractica): Observable<PracticaMalla> {
    return this.http.put<{ data: PracticaMalla }>(`/api/tipos-practica/${id}`, datos).pipe(map(({ data }) => data));
  }

  quitarPractica(id: number): Observable<void> {
    return this.http.delete<void>(`/api/tipos-practica/${id}`);
  }
}
