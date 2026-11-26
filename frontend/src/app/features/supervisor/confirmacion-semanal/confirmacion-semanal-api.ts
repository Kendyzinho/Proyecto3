import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

export type Validacion = 'confirmado' | 'rechazado';

export interface DiaRegistrado {
  id: number;
  fecha: string;
  hora_entrada: string | null;
  hora_salida: string | null;
  horas: number;
  actividades: string | null;
  validacion: Validacion | null;
}

export interface SemanaPorConfirmar {
  estudiante: string;
  centro: string;
  supervisor: string;
  semana: { desde: string; hasta: string };
  confirmada_en: string | null;
  observacion: string | null;
  dias: DiaRegistrado[];
}

export interface Firma {
  expires: string;
  supervisor: string;
  signature: string;
}

export interface Decision {
  dias: { id: number; validacion: Validacion }[];
  observacion: string | null;
}

// HU-26: el supervisor no tiene sesión; cada petición lleva la firma del enlace
@Injectable({ providedIn: 'root' })
export class ConfirmacionSemanalApi {
  private readonly http = inject(HttpClient);

  ver(practica: string, semana: string, firma: Firma): Observable<SemanaPorConfirmar> {
    return this.http.get<{ data: SemanaPorConfirmar }>(ruta(practica, semana), { params: parametros(firma) })
      .pipe(map((r) => r.data));
  }

  confirmar(practica: string, semana: string, firma: Firma, decision: Decision): Observable<SemanaPorConfirmar> {
    return this.http.post<{ data: SemanaPorConfirmar }>(ruta(practica, semana), decision, { params: parametros(firma) })
      .pipe(map((r) => r.data));
  }
}

function ruta(practica: string, semana: string): string {
  return `/api/supervision/practicas/${practica}/semanas/${semana}`;
}

// Laravel firma la query en este orden exacto: expires, supervisor, signature
function parametros(firma: Firma): HttpParams {
  return new HttpParams().set('expires', firma.expires).set('supervisor', firma.supervisor).set('signature', firma.signature);
}
