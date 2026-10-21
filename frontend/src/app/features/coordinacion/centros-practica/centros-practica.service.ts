import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { CentroPractica, EstadoAcuerdo, EstadoCentro } from './centro-practica.model';

interface ApiResponse<T> { data: T }
interface CentroApi {
  id: number;
  nombre: string;
  razon_social: string;
  rut: string | null;
  direccion: string | null;
  telefono: string | null;
  es_interno: boolean;
  estado: string;
  estado_acuerdo: string;
  motivo_baja: string | null;
  areas: Array<{ id: number; nombre: string }>;
  acuerdos: Array<{ tipo: string; representante_legal: string; fecha_inicio: string | null; fecha_termino: string | null; estado: string }>;
  requerimientos: Array<{ categoria: string; cupos: number; ocupados: number; disponibles: number; periodo: { anio: number; semestre: number } }>;
  supervisores: Array<{ id: number; nombre: string; correo: string; cargo: string | null; unidad: string | null; fecha_alta: string | null; fecha_baja: string | null }>;
  estudiantes: Array<{ nombre: string | null; periodo: string | null; estado: string | null; resultado: string | null }>;
}

@Injectable({ providedIn: 'root' })
export class CentrosPracticaService {
  private readonly http = inject(HttpClient);

  listar(): Observable<CentroPractica[]> {
    return this.http.get<ApiResponse<CentroApi[]>>('/api/centros-practica').pipe(
      map(({ data }) => data.map((centro) => this.mapearCentro(centro))),
    );
  }

  private mapearCentro(centro: CentroApi): CentroPractica {
    const acuerdo = centro.acuerdos[0];
    const estadoAcuerdo: EstadoAcuerdo = this.mapearEstadoAcuerdo(centro.estado_acuerdo);
    const estadoCentro: EstadoCentro = centro.estado === 'propuesto'
      ? 'Propuesto'
      : centro.estado === 'dado_de_baja'
        ? 'Dado de baja'
        : 'Activo';

    return {
      id: centro.id,
      nombre: centro.nombre,
      razonSocial: centro.razon_social,
      rut: centro.rut ?? '',
      areas: centro.areas.map((area) => area.nombre),
      acuerdo: estadoAcuerdo,
      cupos: centro.requerimientos.map((requerimiento) => {
        const semestre = requerimiento.periodo.semestre === 1 ? '1°' : '2°';
        return {
          periodo: `${semestre} semestre ${requerimiento.periodo.anio}`,
          categoria: requerimiento.categoria === 'profesional' ? 'Profesional' : 'Laboral',
          total: requerimiento.cupos,
          ocupados: requerimiento.ocupados,
          libres: requerimiento.disponibles,
        };
      }),
      supervisorPrincipal: centro.supervisores[0]?.nombre ?? null,
      estado: estadoCentro,
      direccion: centro.direccion ?? '',
      telefono: centro.telefono ?? '',
      esInterno: centro.es_interno,
      representanteLegal: acuerdo?.representante_legal ?? null,
      tipoAcuerdo: acuerdo?.tipo ?? null,
      vigenciaAcuerdo: acuerdo ? this.vigencia(acuerdo.fecha_inicio, acuerdo.fecha_termino) : null,
      supervisores: centro.supervisores.map((supervisor) => ({
        id: supervisor.id,
        nombre: supervisor.nombre,
        correo: supervisor.correo,
        cargo: supervisor.cargo ?? '',
        unidad: supervisor.unidad ?? '',
        estado: supervisor.fecha_baja ? 'De baja' : supervisor.fecha_alta ? 'Activo' : 'Propuesto',
      })),
      estudiantes: centro.estudiantes.filter((estudiante) => !!estudiante.nombre).map((estudiante) => ({
        nombre: estudiante.nombre!,
        periodo: estudiante.periodo ?? 'Período no informado',
        resultado: this.mapearResultado(estudiante.resultado, estudiante.estado),
      })),
      motivoBaja: centro.motivo_baja ?? undefined,
    };
  }

  private mapearEstadoAcuerdo(estado: string): EstadoAcuerdo {
    const estados: Record<string, EstadoAcuerdo> = {
      vigente: 'Vigente', en_tramite: 'En trámite', vencido: 'Vencido', sin_acuerdo: 'Sin acuerdo', exento: 'Sin acuerdo',
    };
    return estados[estado] ?? 'Sin acuerdo';
  }

  private mapearResultado(resultado: string | null, estado: string | null): 'En curso' | 'Aprobada' | 'Reprobada' {
    if (resultado?.toLowerCase() === 'reprobada') return 'Reprobada';
    if (resultado?.toLowerCase() === 'aprobada') return 'Aprobada';
    return estado === 'en_curso' ? 'En curso' : 'Aprobada';
  }

  private vigencia(inicio: string | null, termino: string | null): string {
    const fecha = (value: string | null) => value ? new Date(`${value}T00:00:00`).toLocaleDateString('es-CL', { month: '2-digit', year: 'numeric' }) : null;
    if (!inicio) return 'Pendiente de firma';
    return `${fecha(inicio)} al ${fecha(termino) ?? 'Sin fecha de término'}`;
  }
}
