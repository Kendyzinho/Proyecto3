import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export type Resultado = 'habilitado' | 'bloqueado' | 'error';

export interface Periodo {
  id: number;
  nombre: string;
}

export interface Practica {
  id: number;
  nombre: string;
  prerrequisito: string | null;
}

export interface Catalogo {
  periodos: Periodo[];
  periodo_actual_id: number | null;
  practicas: Practica[];
}

// Una fila de la planilla después de la revisión del backend
// Lo inválido viene tal como estaba en la planilla
export interface FilaRevisada {
  fila: number;
  rut: string;
  nombre: string;
  correo: string;
  anio_ingreso: number | string;
  practica: string;
  prerrequisito: string | null;
  cumple: boolean | null;
  resultado: Resultado;
  errores: Record<string, string> | [];
}

export interface Resumen {
  total: number;
  habilitados: number;
  bloqueados: number;
  con_error: number;
}

export interface Revision {
  resumen: Resumen;
  filas: FilaRevisada[];
}

// Un estudiante ya guardado en la nómina del período
export interface EstudianteNomina {
  id: number;
  rut: string;
  nombre: string;
  correo: string;
  practica: string;
  prerrequisito: string | null;
  cumple: boolean;
  resultado: Exclude<Resultado, 'error'>;
  origen: 'planilla' | 'manual';
}

export interface NuevoEstudiante {
  periodo_id: number;
  rut: string;
  nombre: string;
  correo: string;
  anio_ingreso: number;
  tipo_practica_id: number;
  cumple_prerrequisito: boolean;
}

// HU-07: carga de la nómina de estudiantes (solo Secretaria de Jefatura)
@Injectable({ providedIn: 'root' })
export class NominaApi {
  private readonly http = inject(HttpClient);

  catalogo(): Observable<Catalogo> {
    return this.http.get<Catalogo>('/api/nomina/catalogo');
  }

  listar(periodoId: number): Observable<{ data: EstudianteNomina[] }> {
    return this.http.get<{ data: EstudianteNomina[] }>('/api/nomina', { params: { periodo_id: periodoId } });
  }

  plantilla(): Observable<Blob> {
    return this.http.get('/api/nomina/plantilla', { responseType: 'blob' });
  }

  revisar(archivo: File, periodoId: number): Observable<Revision> {
    return this.http.post<Revision>('/api/nomina/revisar', formulario(archivo, periodoId));
  }

  confirmar(archivo: File, periodoId: number): Observable<Resumen & { carga_id: number }> {
    return this.http.post<Resumen & { carga_id: number }>('/api/nomina/confirmar', formulario(archivo, periodoId));
  }

  agregar(estudiante: NuevoEstudiante): Observable<{ id: number }> {
    return this.http.post<{ id: number }>('/api/nomina/estudiantes', estudiante);
  }
}

function formulario(archivo: File, periodoId: number): FormData {
  const datos = new FormData();
  datos.append('archivo', archivo);
  datos.append('periodo_id', String(periodoId));
  return datos;
}

// "Laboral I aprobada" pasa a "Laboral I pendiente" cuando no se cumple, como en la pantalla 07
export function textoPrerrequisito(prerrequisito: string | null, cumple: boolean | null): string {
  if (!prerrequisito) {
    return '—';
  }
  return cumple === false ? prerrequisito.replace(/\s+aprobad[oa]$/i, ' pendiente') : prerrequisito;
}

// Guarda en el equipo un archivo que llegó desde la API
export function descargar(contenido: Blob, nombre: string): void {
  const url = URL.createObjectURL(contenido);
  const enlace = document.createElement('a');
  enlace.href = url;
  enlace.download = nombre;
  enlace.click();
  URL.revokeObjectURL(url);
}
