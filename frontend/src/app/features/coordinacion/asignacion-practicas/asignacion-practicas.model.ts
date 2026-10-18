export type CategoriaPractica = 'Laboral' | 'Profesional';

export interface EstudianteAsignable {
  id: number;
  nombre: string;
  carrera: string;
  categoria: CategoriaPractica;
  periodo: string;
  areas: string[];
  habilitado: boolean;
  motivoNoHabilitado?: string;
  practicaAsignada: boolean;
}

export interface CentroAsignable {
  id: number;
  nombre: string;
  rut: string;
  estado: 'Activo' | 'Dado de baja';
  acuerdoVigente: boolean;
  areas: string[];
  cupos: { periodo: string; categoria: CategoriaPractica; total: number; ocupados: number }[];
}

export interface AsignacionPractica {
  estudianteId: number;
  centroId: number;
  periodo: string;
  categoria: CategoriaPractica;
  creadaEn: string;
}
