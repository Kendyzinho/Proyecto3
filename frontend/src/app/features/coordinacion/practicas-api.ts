import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

export interface PeriodoPractica { id: number; nombre: string; }
export interface MetricasPracticas { periodo: PeriodoPractica | null; practicas_registradas: number; }
export interface CatalogoExcepcional {
  periodo: PeriodoPractica;
  habilitaciones: { id: number; estudiante: string; tipo_practica: string }[];
  supervisores: { id: number; nombre: string; centro: string }[];
}
export interface RegistroExcepcional {
  periodo_id: number;
  habilitacion_id: number;
  supervisor_id: number;
  modalidad: string;
  fecha_inicio: string;
  fecha_fin: string;
  justificacion_excepcional: string;
}

@Injectable({ providedIn: 'root' })
export class PracticasApi {
  private readonly http = inject(HttpClient);
  metricas() { return this.http.get<MetricasPracticas>('/api/coordinacion/metricas'); }
  catalogo(periodoId: number) {
    return this.http.get<CatalogoExcepcional>('/api/coordinacion/practicas-excepcionales/catalogo', { params: { periodo_id: periodoId } });
  }
  registrar(datos: RegistroExcepcional) {
    return this.http.post<{ data: { id: number; origen: 'excepcional'; registrada_por: number; created_at: string } }>('/api/coordinacion/practicas-excepcionales', datos);
  }
}
