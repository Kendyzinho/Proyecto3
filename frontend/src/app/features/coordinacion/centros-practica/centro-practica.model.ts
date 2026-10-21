export type EstadoCentro = 'Activo' | 'Propuesto' | 'Dado de baja';
export type EstadoAcuerdo = 'Vigente' | 'En trámite' | 'Vencido' | 'Sin acuerdo';
export type CategoriaPractica = 'Laboral' | 'Profesional';
export type EstadoPractica =
  | 'por_revisar'
  | 'devuelta'
  | 'confirmada'
  | 'en_curso'
  | 'finalizada'
  | 'cancelada';
export type ResultadoDictamen = 'aprobado' | 'reprobado' | null;

export interface CupoCentro {
  periodo: string;
  periodoId?: number;
  categoria: CategoriaPractica;
  total: number;
  ocupados: number;
  libres: number;
}

export interface SupervisorCentro {
  nombre: string;
  cargo: string;
  unidad: string;
  correo: string;
  telefono?: string;
  estado: 'Propuesto' | 'Activo' | 'De baja';
}

export interface EstudianteCentro {
  id?: number;
  nombre: string;
  periodo: string;
  estado: EstadoPractica;
  resultado: ResultadoDictamen;
  fotoUrl?: string | null;
}

export interface CentroPractica {
  id: number;
  nombre: string;
  razonSocial: string;
  rut: string;
  areas: string[];
  acuerdo: EstadoAcuerdo;
  cupos: CupoCentro[];
  supervisorPrincipal: string | null;
  estado: EstadoCentro;
  direccion: string;
  telefono: string;
  esInterno: boolean;
  representanteLegal: string | null;
  tipoAcuerdo: string | null;
  vigenciaAcuerdo: string | null;
  supervisores: SupervisorCentro[];
  estudiantes: EstudianteCentro[];
  motivoBaja?: string;
  fechaAlta?: string | null;
}
