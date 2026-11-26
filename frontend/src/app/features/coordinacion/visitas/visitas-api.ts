import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

export type ModalidadVisita = 'terreno' | 'virtual';
export type EstadoVisita = 'agendada' | 'realizada' | 'cancelada';

export interface PracticaVisita {
  id: number;
  estudiante: string;
  tipo_practica: string;
}

export interface CentroVisita {
  id: number;
  nombre: string;
}

export interface Visita {
  id: number;
  fecha_hora: string; // "2026-12-02 10:30", hora de Chile
  modalidad: ModalidadVisita;
  estado: EstadoVisita;
  centro: CentroVisita | null;
  practicas: PracticaVisita[];
  pautas: { enviadas: number; total: number }; // HU-16
}

// HU-16: formulario de un instrumento (plantilla → criterios → opciones)
export interface OpcionInstrumento {
  id: number;
  etiqueta: string;
  valor: number | null;
}

export interface ItemInstrumento {
  id: number;
  clave: string;
  enunciado: string;
  tipo: string; // 'escala' se responde con una opción; 'texto', escribiendo
  obligatorio: boolean;
  seccion: string | null;
  opciones: OpcionInstrumento[];
}

export interface FormularioInstrumento {
  plantilla_id: number;
  items: ItemInstrumento[];
}

export interface RespuestaItem {
  item_id: number;
  opcion_id?: number | null;
  texto?: string | null;
}

export interface RespuestaGuardada extends FormularioInstrumento {
  respuesta_id: number;
  enviada_en: string | null; // sin fecha es un borrador
  respuestas: RespuestaItem[];
}

export interface PracticaPauta extends PracticaVisita {
  respuesta: RespuestaGuardada | null;
}

export interface PautasVisita {
  visita: Visita;
  formulario: FormularioInstrumento | null;
  practicas: PracticaPauta[];
}

export interface DatosPauta {
  respuestas: RespuestaItem[];
  enviar: boolean;
}

export interface CentroSinVisita extends CentroVisita {
  practicas: PracticaVisita[];
}

export interface PracticaDisponible extends PracticaVisita {
  centro: CentroVisita;
}

export interface DatosVisita {
  fecha_hora: string;
  modalidad: ModalidadVisita;
  practicas: number[];
}

const BASE = '/api/coordinacion/visitas';

// HU-15: agenda de visitas técnicas
@Injectable({ providedIn: 'root' })
export class VisitasApi {
  private readonly http = inject(HttpClient);

  listar(): Observable<Visita[]> {
    return this.http.get<{ data: Visita[] }>(BASE).pipe(map((r) => r.data));
  }

  centrosSinVisita(): Observable<CentroSinVisita[]> {
    return this.http.get<{ data: CentroSinVisita[] }>(`${BASE}/centros-sin-visita`).pipe(map((r) => r.data));
  }

  practicasDisponibles(): Observable<PracticaDisponible[]> {
    return this.http.get<{ data: PracticaDisponible[] }>(`${BASE}/practicas-disponibles`).pipe(map((r) => r.data));
  }

  crear(datos: DatosVisita): Observable<Visita> {
    return this.http.post<{ data: Visita }>(BASE, datos).pipe(map((r) => r.data));
  }

  editar(id: number, datos: DatosVisita): Observable<Visita> {
    return this.http.put<{ data: Visita }>(`${BASE}/${id}`, datos).pipe(map((r) => r.data));
  }

  cancelar(id: number): Observable<Visita> {
    return this.http.post<{ data: Visita }>(`${BASE}/${id}/cancelar`, {}).pipe(map((r) => r.data));
  }

  // HU-16
  pautas(visita: number): Observable<PautasVisita> {
    return this.http.get<{ data: PautasVisita }>(`${BASE}/${visita}/pautas`).pipe(map((r) => r.data));
  }

  guardarPauta(visita: number, practica: number, datos: DatosPauta): Observable<RespuestaGuardada> {
    return this.http.put<{ data: RespuestaGuardada }>(`${BASE}/${visita}/pautas/${practica}`, datos).pipe(map((r) => r.data));
  }
}
