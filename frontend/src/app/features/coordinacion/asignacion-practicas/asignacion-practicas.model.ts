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
