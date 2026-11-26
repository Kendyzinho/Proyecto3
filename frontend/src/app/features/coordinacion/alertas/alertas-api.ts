import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

export type TipoAlerta = 'abandono' | 'inasistencia' | 'sin_confirmar';
export type EstadoAlertas = 'abiertas' | 'atendidas';

// Una alerta tal como la entrega la API
export interface Alerta {
  id: number;
  tipo: TipoAlerta;
  fecha_referencia: string; // día que la originó (yyyy-mm-dd); en las amarillas, el lunes de la semana
  fecha_generada: string;
  atendida: boolean;
  fecha_atencion: string | null;
  atendida_por: string | null;
  practica: {
    id: number;
    estudiante: string;
    correo: string;
    tipo_practica: string;
    centro: string | null;
    supervisor: string | null;
  };
}

export interface ListaAlertas {
  data: Alerta[];
  meta: { abiertas: number; atendidas: number };
}

// Color de la luz y cómo se explica cada tipo (lo usan el panel de alertas y la campana)
export const TIPOS_ALERTA: Record<TipoAlerta, { titulo: string; luz: 'roja' | 'naranja' | 'amarilla'; detalle: (fecha: string) => string }> = {
  abandono: { titulo: 'Posible abandono', luz: 'roja', detalle: (f) => `3 días esperados seguidos sin marca desde el ${f}` },
  inasistencia: { titulo: 'Inasistencia sin justificar', luz: 'naranja', detalle: (f) => `Faltó el ${f} y no justificó en 24 horas` },
  sin_confirmar: { titulo: 'Semana sin confirmar', luz: 'amarilla', detalle: (f) => `El supervisor no confirmó la semana del ${f}` },
};

// HU-14: alertas tempranas de la Coordinadora
@Injectable({ providedIn: 'root' })
export class AlertasApi {
  private readonly http = inject(HttpClient);

  listar(estado: EstadoAlertas): Observable<ListaAlertas> {
    return this.http.get<ListaAlertas>('/api/coordinacion/alertas', { params: { estado } });
  }

  atender(id: number): Observable<Alerta> {
    return this.http.post<{ data: Alerta }>(`/api/coordinacion/alertas/${id}/atender`, {}).pipe(map(({ data }) => data));
  }
}

// 2026-11-16 o 2026-11-18T09:30:00-03:00 → 16-11-2026
export function fechaLegible(fecha: string): string {
  if (fecha.length === 10) {
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}-${mes}-${anio}`;
  }
  const d = new Date(fecha);
  return `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`;
}