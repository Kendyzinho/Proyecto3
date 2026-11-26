export type ValidacionDiaria = 'pendiente' | 'confirmado' | 'rechazado';
export type TipoRegistroDiario = 'jornada' | 'inasistencia';

export interface RegistroDiario {
  id: number;
  fecha: string;
  hora_entrada: string | null;
  hora_salida: string | null;
  actividades: string | null;
  validacion: ValidacionDiaria;
  justificacion: string | null;
  fecha_justificacion: string | null;
  recuperacion_programada: string | null;
  es_inasistencia: boolean;
  es_recuperacion: boolean;
  horas: number;
  editable: boolean;
}

export interface PracticaBitacora {
  id: number;
  tipo_practica: string | null;
  centro: string | null;
  estado: string | null;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  horas_requeridas: number;
  minutos_confirmados: number;
  horas_confirmadas: number;
  porcentaje_avance: number;
  puede_registrar: boolean;
  registros?: RegistroDiario[];
  horario?: Array<{ vigente_desde: string; dias: Array<{ dia_semana: number; hora_inicio: string; hora_fin: string }> }>;
}

export interface GuardarJornada {
  tipo: 'jornada';
  hora_entrada: string;
  hora_salida: string;
  actividades: string;
}

export interface InformarInasistencia {
  tipo: 'inasistencia';
  justificacion: string;
  recuperacion_programada: string;
}
