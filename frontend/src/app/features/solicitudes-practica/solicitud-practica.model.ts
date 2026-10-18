export type EstadoSolicitud = 'Borrador' | 'Por revisar' | 'Devuelta' | 'Aprobada';
export type ModalidadPractica = 'Presencial' | 'A distancia' | 'Mixta';
export type ModoCentro = 'Registrado' | 'Propuesto';
export type ModoSupervisor = 'Registrado' | 'Propuesto';

export interface HorarioPropuesto {
  modalidad: ModalidadPractica | '';
  fechaInicio: string;
  fechaTermino: string;
  dias: string[];
  horaDesde: string;
  horaHasta: string;
}

export interface SolicitudPractica {
  id: number;
  estudianteNombre: string;
  estudianteCorreo: string;
  estado: EstadoSolicitud;
  habilitacionCumplida: boolean;
  modoCentro: ModoCentro;
  centroId: number | null;
  centroRazonSocial: string;
  centroRut: string;
  modoSupervisor: ModoSupervisor;
  supervisorId: string | null;
  supervisorNombre: string;
  supervisorCorreo: string;
  supervisorCargo: string;
  supervisorTelefono: string;
  supervisorUnidad: string;
  tareas: string;
  horario: HorarioPropuesto;
  motivoDevolucion: string | null;
  actualizadaEn: string;
}

export type SolicitudFormulario = Omit<SolicitudPractica, 'id' | 'estudianteNombre' | 'estudianteCorreo' | 'estado' | 'habilitacionCumplida' | 'motivoDevolucion' | 'actualizadaEn'>;

export interface HistorialDecision {
  solicitudId: number;
  decision: 'Aprobada' | 'Devuelta' | 'Reenviada';
  motivo: string | null;
  responsable: string;
  fecha: string;
}
