// HU-14: alertas tempranas sobre una práctica en curso
export type TipoAlerta = 'abandono' | 'inasistencia' | 'sin_confirmar';

export const LUZ_ALERTA: Record<TipoAlerta, 'roja' | 'naranja' | 'amarilla'> = {
  abandono: 'roja',
  inasistencia: 'naranja',
  sin_confirmar: 'amarilla',
};

// Qué pasó, contado desde la Coordinación (la fecha es el día o el lunes que originó la alerta)
export function textoAlerta(tipo: TipoAlerta, fecha: string | null): string {
  const dia = fechaCorta(fecha);
  switch (tipo) {
    case 'abandono':
      return `3 días seguidos sin marcar desde el ${dia}`;
    case 'inasistencia':
      return `Faltó el ${dia} y no lo justificó`;
    case 'sin_confirmar':
      return `Semana del ${dia} sin confirmar por el supervisor`;
  }
}

// 2026-11-16 → 16/11; con hora (ISO) usa la fecha local
export function fechaCorta(fecha: string | null): string {
  if (!fecha) return '';
  if (fecha.length === 10) {
    const [, mes, dia] = fecha.split('-');
    return `${dia}/${mes}`;
  }
  const d = new Date(fecha);
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`;
}
