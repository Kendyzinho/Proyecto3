export type CategoriaPractica = 'Laboral';
export type ModalidadPractica = 'presencial' | 'a_distancia' | 'mixta';

export interface EstudianteAsignable {
  id: number;
  nombre: string;
  carrera: string;
  categoria: CategoriaPractica;
  periodo: string;
  periodoId: number;
  areas: string[];
  habilitado: boolean;
  motivoNoHabilitado?: string;
  practicaAsignada: boolean;
}

export interface CentroAsignable {
  id: number;
  nombre: string;
  rut: string;
  direccion: string;
  estado: 'Activo' | 'Dado de baja';
  acuerdoVigente: boolean;
  areas: string[];
  cupos: { periodo: string; categoria: CategoriaPractica; total: number; ocupados: number }[];
  supervisores: { id: number; nombre: string; correo: string }[];
}

export interface AsignacionPractica {
  estudianteId: number;
  centroId: number;
  periodo: string;
  categoria: CategoriaPractica;
  creadaEn: string;
}

export const DIAS_SEMANA = [
  { valor: 1, etiqueta: 'Lun' }, { valor: 2, etiqueta: 'Mar' }, { valor: 3, etiqueta: 'Mié' }, { valor: 4, etiqueta: 'Jue' },
  { valor: 5, etiqueta: 'Vie' }, { valor: 6, etiqueta: 'Sáb' }, { valor: 7, etiqueta: 'Dom' },
] as const;

export interface HorarioPractica {
  dias: number[];
  hora_desde: string;
  hora_hasta: string;
}

export interface CalendarioPractica {
  id: number;
  estudiante: string;
  tipo_practica: string;
  centro: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  horario: HorarioPractica | null;
}

export interface AreaOpcion {
  id: number;
  nombre: string;
}
