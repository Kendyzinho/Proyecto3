import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, concatMap, forkJoin, from, map, of, switchMap, toArray } from 'rxjs';

import {
  CentroPractica,
  CupoCentro,
  EstadoAcuerdo,
  EstadoCentro,
  EstadoPractica,
  ResultadoDictamen,
  SupervisorCentro,
} from './centro-practica.model';

interface Respuesta<T> {
  data: T;
}

interface AreaApi {
  id: number;
  nombre: string;
}

interface PeriodoApi {
  id: number;
  anio: number;
  semestre: number;
}

interface CatalogoInicial {
  centros: CentroPractica[];
  areas: AreaApi[];
  periodos: PeriodoApi[];
}

interface CentroApi {
  id: number;
  nombre: string;
  razon_social: string;
  rut: string | null;
  direccion: string | null;
  telefono: string | null;
  es_interno: boolean;
  fecha_alta: string | null;
  fecha_baja: string | null;
  motivo_baja: string | null;
  estado: string;
  estado_acuerdo: string;
  areas: AreaApi[];
  acuerdos: Array<{
    id?: number;
    tipo?: string;
    representante_legal?: string;
    fecha_inicio?: string | null;
    fecha_termino?: string | null;
    archivo_pdf?: string | null;
  }>;
  requerimientos: Array<{
    periodo_id: number;
    categoria: string;
    cupos: number;
    ocupados: number;
    disponibles: number;
    periodo?: PeriodoApi;
  }>;
  supervisores: Array<{
    id?: number;
    nombre: string;
    cargo: string | null;
    unidad: string | null;
    correo: string;
    telefono: string | null;
    fecha_alta: string | null;
    fecha_baja: string | null;
  }>;
  estudiantes: Array<{
    practica_id: number;
    nombre: string | null;
    periodo: string | null;
    estado: string | null;
    resultado: string | null;
  }>;
}

@Injectable({ providedIn: 'root' })
export class CentrosPracticaService {
  private readonly http = inject(HttpClient);

  listar(): Observable<CatalogoInicial> {
    return forkJoin({
      centros: this.http.get<Respuesta<CentroApi[]>>('/api/centros-practica'),
      areas: this.http.get<Respuesta<AreaApi[]>>('/api/areas'),
      periodos: this.http.get<Respuesta<PeriodoApi[]>>('/api/periodos'),
    }).pipe(
      map(({ centros, areas, periodos }) => ({
        centros: centros.data.map((centro) => this.mapearCentro(centro)),
        areas: areas.data,
        periodos: periodos.data,
      })),
    );
  }

  guardar(centro: CentroPractica, id?: number): Observable<CentroPractica> {
    return this.http.get<Respuesta<AreaApi[]>>('/api/areas').pipe(
      switchMap(({ data: areas }) =>
        from(centro.areas).pipe(
          concatMap((nombre) => this.resolverArea(nombre, areas)),
          toArray(),
          map((areaIds) => [...new Set(areaIds)]),
          switchMap((areaIds) => this.payloadCentro(centro, areaIds, id === undefined)),
          switchMap((payload) =>
            id
              ? this.http.put<Respuesta<CentroApi>>(`/api/centros-practica/${id}`, payload)
              : this.http.post<Respuesta<CentroApi>>('/api/centros-practica', payload),
          ),
          map((respuesta) => this.mapearCentro(respuesta.data)),
        ),
      ),
    );
  }

  darDeBaja(id: number, motivo: string): Observable<CentroPractica> {
    return this.http
      .post<Respuesta<CentroApi>>(`/api/centros-practica/${id}/dar-de-baja`, { motivo_baja: motivo })
      .pipe(map(({ data }) => this.mapearCentro(data)));
  }

  reactivar(id: number): Observable<CentroPractica> {
    return this.http
      .post<Respuesta<CentroApi>>(`/api/centros-practica/${id}/reactivar`, {})
      .pipe(map(({ data }) => this.mapearCentro(data)));
  }

  archivoAcuerdo(acuerdoId: number): Observable<Blob> {
    return this.http.get(`/api/acuerdos/${acuerdoId}/archivo`, { responseType: 'blob' });
  }

  agregarSupervisor(centroId: number, datos: Omit<SupervisorCentro, 'id' | 'estado'>): Observable<CentroPractica> {
    return this.http
      .post<Respuesta<CentroApi>>(`/api/centros-practica/${centroId}/supervisores`, datos)
      .pipe(map(({ data }) => this.mapearCentro(data)));
  }

  activarSupervisor(centroId: number, supervisorId: number): Observable<CentroPractica> {
    return this.http
      .post<Respuesta<CentroApi>>(`/api/centros-practica/${centroId}/supervisores/${supervisorId}/activar`, {})
      .pipe(map(({ data }) => this.mapearCentro(data)));
  }

  darDeBajaSupervisor(centroId: number, supervisorId: number): Observable<CentroPractica> {
    return this.http
      .post<Respuesta<CentroApi>>(`/api/centros-practica/${centroId}/supervisores/${supervisorId}/dar-de-baja`, {})
      .pipe(map(({ data }) => this.mapearCentro(data)));
  }

  mensajeError(error: unknown): string {
    const response = error as {
      error?: { message?: string; errors?: Record<string, string[]> };
      message?: string;
    };
    const firstValidationMessage = Object.values(response?.error?.errors ?? {})[0]?.[0];
    return firstValidationMessage ?? response?.error?.message ?? response?.message ??
      'No se pudo completar la operación. Revisa que el backend esté encendido e inténtalo otra vez.';
  }

  private resolverArea(nombre: string, areas: AreaApi[]): Observable<number> {
    const existente = areas.find((area) => area.nombre.toLocaleLowerCase('es') === nombre.toLocaleLowerCase('es'));
    if (existente) return of(existente.id);

    return this.http.post<Respuesta<AreaApi>>('/api/areas', { nombre }).pipe(map(({ data }) => data.id));
  }

  private payloadCentro(centro: CentroPractica, areaIds: number[], esNuevo: boolean): Observable<Record<string, unknown>> {
    return this.http.get<Respuesta<PeriodoApi[]>>('/api/periodos').pipe(
      map(({ data: periodos }) => {
        const requerimientos = centro.cupos.map((cupo) => {
          const periodo = periodos.find((item) => item.id === cupo.periodoId) ??
            periodos.find((item) => this.periodoCoincide(cupo.periodo, item));
          if (!periodo) {
            throw new Error(`No existe el período «${cupo.periodo}» en el sistema. Regístralo primero desde la gestión de períodos.`);
          }
          return {
            periodo_id: periodo.id,
            categoria: cupo.categoria.toLocaleLowerCase('es'),
            cupos: cupo.total,
            area_ids: areaIds,
          };
        });

        return {
          nombre: centro.nombre,
          razon_social: centro.razonSocial,
          rut: centro.rut,
          direccion: centro.direccion,
          telefono: centro.telefono,
          es_interno: centro.esInterno,
          area_ids: areaIds,
          requerimientos,
          ...(esNuevo ? {
            supervisores: centro.supervisores.map((supervisor) => ({
              nombre: supervisor.nombre,
              correo: supervisor.correo,
              cargo: supervisor.cargo || null,
              unidad: supervisor.unidad || null,
              telefono: supervisor.telefono || null,
            })),
          } : {}),
        };
      }),
    );
  }

  private periodoCoincide(etiqueta: string, periodo: PeriodoApi): boolean {
    const match = etiqueta.match(/([12])\D*sem(?:estre)?\D*(\d{4})/i);
    return !!match && Number(match[1]) === periodo.semestre && Number(match[2]) === periodo.anio;
  }

  private mapearCentro(centro: CentroApi): CentroPractica {
    const acuerdo = centro.acuerdos[0];
    const supervisorPrincipal = centro.supervisores?.find((supervisor) => !supervisor.fecha_baja);
    return {
      id: centro.id,
      nombre: centro.nombre,
      razonSocial: centro.razon_social,
      rut: centro.rut ?? '',
      areas: (centro.areas ?? []).map((area) => area.nombre),
      acuerdo: this.mapearEstadoAcuerdo(centro.estado_acuerdo),
      cupos: (centro.requerimientos ?? []).map((requerimiento) => {
        const periodo = requerimiento.periodo;
        const etiquetaPeriodo = periodo
          ? `${periodo.semestre}° sem ${periodo.anio}`
          : 'Período sin datos';
        return {
          periodo: etiquetaPeriodo,
          periodoId: requerimiento.periodo_id,
          categoria: requerimiento.categoria === 'profesional' ? 'Profesional' : 'Laboral',
          total: requerimiento.cupos,
          ocupados: requerimiento.ocupados,
          libres: requerimiento.disponibles,
        } satisfies CupoCentro;
      }),
      supervisorPrincipal: supervisorPrincipal
        ? `${supervisorPrincipal.nombre}${supervisorPrincipal.fecha_alta ? '' : ' (Propuesto)'}`
        : null,
      estado: this.mapearEstadoCentro(centro.estado),
      direccion: centro.direccion ?? '',
      telefono: centro.telefono ?? '',
      esInterno: centro.es_interno,
      representanteLegal: acuerdo?.representante_legal ?? null,
      tipoAcuerdo: acuerdo?.tipo ?? null,
      vigenciaAcuerdo: this.vigencia(acuerdo?.fecha_inicio, acuerdo?.fecha_termino),
      acuerdoArchivoId: acuerdo?.archivo_pdf && acuerdo.id ? acuerdo.id : null,
      supervisores: (centro.supervisores ?? []).map((supervisor): SupervisorCentro => ({
        id: supervisor.id,
        nombre: supervisor.nombre,
        cargo: supervisor.cargo ?? '',
        unidad: supervisor.unidad ?? '',
        correo: supervisor.correo,
        telefono: supervisor.telefono ?? '',
        estado: supervisor.fecha_baja ? 'De baja' : supervisor.fecha_alta ? 'Activo' : 'Propuesto',
      })),
      estudiantes: (centro.estudiantes ?? []).flatMap((estudiante) => {
        if (!estudiante.nombre || !this.esEstadoPractica(estudiante.estado)) return [];
        return [{
          id: estudiante.practica_id,
          nombre: estudiante.nombre,
          periodo: estudiante.periodo ?? 'Sin período',
          estado: estudiante.estado,
          resultado: this.esResultado(estudiante.resultado) ? estudiante.resultado : null,
        }];
      }),
      motivoBaja: centro.motivo_baja ?? undefined,
      fechaAlta: centro.fecha_alta,
    };
  }

  private mapearEstadoCentro(estado: string): EstadoCentro {
    if (estado === 'propuesto') return 'Propuesto';
    if (estado === 'sin_acuerdo') return 'Sin acuerdo';
    if (estado === 'dado_de_baja') return 'Dado de baja';
    // Una baja futura sigue siendo seleccionable hasta la fecha efectiva.
    return 'Activo';
  }

  private mapearEstadoAcuerdo(estado: string): EstadoAcuerdo {
    switch (estado) {
      case 'vigente': return 'Vigente';
      case 'en_tramite': return 'En trámite';
      case 'vencido': return 'Vencido';
      default: return 'Sin acuerdo';
    }
  }

  private esEstadoPractica(estado: string | null): estado is EstadoPractica {
    return ['por_revisar', 'devuelta', 'confirmada', 'en_curso', 'finalizada', 'cancelada'].includes(estado ?? '');
  }

  private esResultado(resultado: string | null): resultado is Exclude<ResultadoDictamen, null> {
    return resultado === 'aprobado' || resultado === 'reprobado';
  }

  private vigencia(inicio?: string | null, termino?: string | null): string | null {
    if (!inicio) return null;
    const formato = (fecha: string) => {
      const [anio, mes] = fecha.split('-');
      return `${mes}-${anio}`;
    };
    return `${formato(inicio)} al ${termino ? formato(termino) : 'sin término'}`;
  }
}
