export interface FichaEstudianteDatos {
  estudiante: { id: number; nombre: string; rut: string | null; correo: string; anio_ingreso: number | null };
  practica: {
    id: number;
    tipo: string | null;
    periodo: string | null;
    centro: string | null;
    supervisor: string | null;
    estado: string | null;
    fecha_inicio: string | null;
    fecha_fin: string | null;
  };
  bitacora: { horas_requeridas: number; minutos_confirmados: number; horas_confirmadas: number; porcentaje_avance: number; jornadas: number };
  documentos: Array<{ tipo: 'carta' | 'seguro'; etiqueta: string; registrado: boolean; archivo_o_url: string | null; fecha: string | null; fecha_envio: string | null }>;
  visitas: Array<{ id: number; fecha_hora: string; modalidad: 'terreno' | 'virtual'; cancelada: boolean }>;
  evaluacion: { resultado: 'aprobado' | 'reprobado' | null; estado: string; fecha: string | null; motivo: string | null };
}
