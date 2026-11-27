import { Acuerdo } from '../../secretaria/documentos/documentos-api';

export type EstadoCentro = 'Activo' | 'Propuesto' | 'Sin acuerdo' | 'Dado de baja';
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
  id?: number;
  nombre: string;
  cargo: string;
  unidad: string;
  correo: string;
  telefono?: string;
  estado: 'Propuesto' | 'Activo' | 'De baja';
}

// Respuesta de la búsqueda por correo del diálogo "Agregar supervisor(a)"
export interface CuentaSupervisorEncontrada {
  cuenta: { nombre: string; activa: boolean } | null; // ya tiene cuenta de supervisor(a)
  interna: boolean; // el correo es de una cuenta de la universidad: no sirve
  en_este_centro: boolean; // ya está registrado en este centro
}

// Un resultado del buscador por nombre o correo del diálogo "Agregar supervisor(a)"
export interface CuentaSupervisorResultado {
  nombre: string;
  correo: string;
  activa: boolean;
  en_este_centro: boolean;
}

export interface DatosAsignarSupervisor {
  correo: string;
  nombre: string | null; // solo si no tiene cuenta
  cargo: string | null;
  unidad: string | null;
  telefono: string | null;
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
  acuerdoArchivoId?: number | null;
  acuerdoDatos?: Acuerdo | null;
  supervisores: SupervisorCentro[];
  estudiantes: EstudianteCentro[];
  motivoBaja?: string;
  fechaAlta?: string | null;
}
