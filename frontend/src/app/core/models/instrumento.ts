// Formulario de un instrumento configurable (plantilla → criterios → opciones).
// Lo usan la pauta de visita (HU-16) y el informe confidencial (HU-19).
export interface OpcionInstrumento {
  id: number;
  etiqueta: string;
  valor: number | null;
}

export interface ItemInstrumento {
  id: number;
  clave: string;
  enunciado: string;
  tipo: string; // 'escala' se responde con una opción; 'texto', escribiendo
  obligatorio: boolean;
  seccion: string | null;
  opciones: OpcionInstrumento[];
}

export interface FormularioInstrumento {
  plantilla_id: number;
  items: ItemInstrumento[];
}

export interface RespuestaItem {
  item_id: number;
  opcion_id?: number | null;
  texto?: string | null;
}

export interface RespuestaGuardada extends FormularioInstrumento {
  respuesta_id: number;
  enviada_en: string | null; // sin fecha es un borrador
  respuestas: RespuestaItem[];
}
