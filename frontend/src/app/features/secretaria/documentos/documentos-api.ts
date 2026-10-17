import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export type TipoDocumento = 'carta' | 'seguro';
export type EstadoDocumento = 'por_generar' | 'generada' | 'enviada' | 'sin_acuerdo' | 'en_espera';
export type EstadoAcuerdo = 'vigente' | 'en_tramite' | 'vencido';

export interface PeriodoActual {
  id: number;
  nombre: string;
}

// Una práctica del período con el estado de su carta y su guía del seguro
export interface PracticaPorFormalizar {
  id: number;
  estudiante: string;
  correo: string;
  practica: string;
  centro: string;
  supervisor: string;
  correo_supervisor: string;
  con_acuerdo: boolean;
  carta: { estado: EstadoDocumento; documento_id: number | null };
  seguro: { estado: EstadoDocumento; documento_id: number | null };
}

export interface ContenidoCarta {
  unidad: string;
  lugar_fecha: string;
  destinatario: string[];
  parrafos: string[];
  firma: [string, string];
}

export interface ContenidoSeguro {
  unidad: string;
  titulo: string;
  datos: Record<string, string>;
  secciones: { titulo: string; texto?: string; pasos?: string[] }[];
}

export interface Acuerdo {
  id: number;
  tipo: 'tradicional' | 'con_equipamiento';
  representante_legal: string;
  fecha_inicio: string | null;
  fecha_termino: string | null;
  estado: EstadoAcuerdo;
  tiene_archivo: boolean;
}

export interface DetallePractica extends PracticaPorFormalizar {
  textos: { carta: ContenidoCarta; seguro: ContenidoSeguro };
  centro_id: number;
  centro_interno: boolean;
  acuerdo: Acuerdo | null;
}

export interface DatosAcuerdo {
  tipo: Acuerdo['tipo'];
  representante_legal: string;
  fecha_inicio: string;
  fecha_termino: string;
  archivo: File | null;
}

// HU-10: carta de presentación, guía del seguro escolar y acuerdo de colaboración
@Injectable({ providedIn: 'root' })
export class DocumentosApi {
  private readonly http = inject(HttpClient);

  periodoActual(): Observable<PeriodoActual | null> {
    return this.http.get<PeriodoActual | null>('/api/periodos/actual');
  }

  listar(periodoId: number): Observable<{ data: PracticaPorFormalizar[] }> {
    return this.http.get<{ data: PracticaPorFormalizar[] }>('/api/documentos', { params: { periodo_id: periodoId } });
  }

  detalle(practicaId: number): Observable<DetallePractica> {
    return this.http.get<DetallePractica>(`/api/practicas/${practicaId}/documentos`);
  }

  // Descarga el PDF; si todavía no existe, el backend lo genera
  pdf(practicaId: number, tipo: TipoDocumento): Observable<Blob> {
    return this.http.get(`/api/practicas/${practicaId}/documentos/${tipo}`, { responseType: 'blob' });
  }

  enviar(practicaId: number): Observable<{ destinatarios: string[] }> {
    return this.http.post<{ destinatarios: string[] }>(`/api/practicas/${practicaId}/documentos/enviar`, {});
  }

  guardarAcuerdo(centroId: number, datos: DatosAcuerdo): Observable<{ id: number; estado: EstadoAcuerdo }> {
    const formulario = new FormData();
    formulario.append('tipo', datos.tipo);
    formulario.append('representante_legal', datos.representante_legal);
    formulario.append('fecha_inicio', datos.fecha_inicio);
    formulario.append('fecha_termino', datos.fecha_termino);
    if (datos.archivo) {
      formulario.append('archivo', datos.archivo);
    }
    return this.http.post<{ id: number; estado: EstadoAcuerdo }>(`/api/centros/${centroId}/acuerdo`, formulario);
  }

  archivoAcuerdo(acuerdoId: number): Observable<Blob> {
    return this.http.get(`/api/acuerdos/${acuerdoId}/archivo`, { responseType: 'blob' });
  }
}

// Texto y color de cada estado. La carta es femenina y el seguro masculino, como en la pantalla 08
export function textoEstado(estado: EstadoDocumento, tipo: TipoDocumento): string {
  const textos: Record<EstadoDocumento, [string, string]> = {
    por_generar: ['Por generar', 'Por generar'],
    generada: ['Generada', 'Generado'],
    enviada: ['Enviada', 'Enviado'],
    sin_acuerdo: ['Sin acuerdo', 'Sin acuerdo'],
    en_espera: ['En espera', 'En espera'],
  };
  return textos[estado][tipo === 'carta' ? 0 : 1];
}

export function claseEstado(estado: EstadoDocumento): string {
  const clases: Record<EstadoDocumento, string> = {
    por_generar: 'estado--advertencia',
    generada: 'estado--info',
    enviada: 'estado--exito',
    sin_acuerdo: 'estado--error',
    en_espera: 'estado--neutro',
  };
  return clases[estado];
}

export const TEXTO_ACUERDO: Record<EstadoAcuerdo, string> = {
  vigente: 'Vigente',
  en_tramite: 'En trámite',
  vencido: 'Vencido',
};
