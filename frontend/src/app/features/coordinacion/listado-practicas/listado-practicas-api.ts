import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export type EstadoOperativo = 'en_curso' | 'posible_abandono' | 'por_iniciar';

export interface PracticaListada {
  id: number;
  rut: string;
  estudiante: string;
  correo: string;
  tipo_practica_id: number;
  practica: string;
  centro: string;
  supervisor: string;
  estado: string;
  estado_texto: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
}

export interface CatalogoListado {
  tipos: { id: number; nombre: string }[];
  estados: { valor: EstadoOperativo; texto: string }[];
}

export interface ListadoPracticas {
  periodo: { id: number; nombre: string } | null;
  catalogo: CatalogoListado;
  data: PracticaListada[];
}

export interface FiltrosListado {
  q: string;
  tipo_practica_id: number | null;
  estado: EstadoOperativo | null;
}

export const FILTROS_VACIOS: FiltrosListado = { q: '', tipo_practica_id: null, estado: null };

@Injectable({ providedIn: 'root' })
export class ListadoPracticasApi {
  private readonly http = inject(HttpClient);

  listar(filtros: FiltrosListado = FILTROS_VACIOS): Observable<ListadoPracticas> {
    return this.http.get<ListadoPracticas>('/api/coordinacion/practicas', {
      params: this.parametros(filtros),
    });
  }

  exportar(filtros: FiltrosListado = FILTROS_VACIOS): Observable<Blob> {
    return this.http.get('/api/coordinacion/practicas/exportar', {
      params: this.parametros(filtros),
      responseType: 'blob',
    });
  }

  private parametros(filtros: FiltrosListado): HttpParams {
    let parametros = new HttpParams();
    if (filtros.q.trim()) {
      parametros = parametros.set('q', filtros.q.trim());
    }
    if (filtros.tipo_practica_id !== null) {
      parametros = parametros.set('tipo_practica_id', String(filtros.tipo_practica_id));
    }
    if (filtros.estado !== null) {
      parametros = parametros.set('estado', filtros.estado);
    }
    return parametros;
  }
}

export function filtrarPracticas(
  filas: PracticaListada[],
  filtros: FiltrosListado,
): PracticaListada[] {
  const texto = normalizar(filtros.q);

  return filas.filter((fila) => {
    if (filtros.tipo_practica_id !== null && fila.tipo_practica_id !== filtros.tipo_practica_id) {
      return false;
    }
    if (filtros.estado !== null && fila.estado !== filtros.estado) {
      return false;
    }
    if (!texto) {
      return true;
    }

    return [
      fila.rut,
      fila.estudiante,
      fila.correo,
      fila.practica,
      fila.centro,
      fila.supervisor,
      fila.estado_texto,
    ].some((valor) => normalizar(valor).includes(texto));
  });
}

function normalizar(valor: string): string {
  return valor
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}
