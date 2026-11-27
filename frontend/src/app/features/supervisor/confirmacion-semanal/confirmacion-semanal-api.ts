import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

export type Validacion = 'confirmado' | 'rechazado';

export interface Rango {
  desde: string;
  hasta: string;
}

export interface DiaRegistrado {
  id: number;
  fecha: string;
  hora_entrada: string | null;
  hora_salida: string | null;
  horas: number;
  actividades: string | null;
  justificacion: string | null;
  validacion: Validacion | null;
}

export interface SemanaPorConfirmar {
  practica_id: number;
  estudiante: string;
  centro: string;
  semana: Rango;
  confirmada_en: string | null;
  observacion: string | null;
  pendientes: number;
  dias: DiaRegistrado[];
}

export interface FilaSemana {
  practica_id: number;
  estudiante: string;
  centro: string;
  semana: Rango;
  dias: number;
  pendientes: number;
  confirmada_en: string | null;
}

export interface Decision {
  dias: { id: number; validacion: Validacion }[];
  observacion: string | null;
}

// HU-26: el supervisor confirma con su sesión 
@Injectable({ providedIn: 'root' })
export class ConfirmacionSemanalApi {
  private readonly http = inject(HttpClient);

  listar(): Observable<FilaSemana[]> {
    return this.http.get<{ data: FilaSemana[] }>('/api/supervisor/asistencia').pipe(map((r) => r.data));
  }

  ver(practica: number, semana: string): Observable<SemanaPorConfirmar> {
    return this.http.get<{ data: SemanaPorConfirmar }>(ruta(practica, semana)).pipe(map((r) => r.data));
  }

  confirmar(practica: number, semana: string, decision: Decision): Observable<SemanaPorConfirmar> {
    return this.http.post<{ data: SemanaPorConfirmar }>(ruta(practica, semana), decision).pipe(map((r) => r.data));
  }
}

function ruta(practica: number, semana: string): string {
  return `/api/supervisor/asistencia/${practica}/${semana}`;
}


export function fechaCorta(fecha: string): string {
  return fecha.split('-').reverse().join('/');
}