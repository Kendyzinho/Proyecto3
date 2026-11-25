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
