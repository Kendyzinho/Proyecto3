import { Injectable, Provider } from '@angular/core';
import { MAT_DATE_LOCALE } from '@angular/material/core';
import { MatDatepickerIntl } from '@angular/material/datepicker';
import { provideDateFnsAdapter } from '@angular/material-date-fns-adapter';
import { format, isValid } from 'date-fns';
import { es } from 'date-fns/locale';

// Textos del calendario de Material en español
@Injectable()
export class CalendarioEnEspanol extends MatDatepickerIntl {
  override calendarLabel = 'Calendario';
  override openCalendarLabel = 'Abrir calendario';
  override closeCalendarLabel = 'Cerrar calendario';
  override prevMonthLabel = 'Mes anterior';
  override nextMonthLabel = 'Mes siguiente';
  override prevYearLabel = 'Año anterior';
  override nextYearLabel = 'Año siguiente';
  override prevMultiYearLabel = 'Años anteriores';
  override nextMultiYearLabel = 'Años siguientes';
  override switchToMonthViewLabel = 'Elegir día';
  override switchToMultiYearViewLabel = 'Elegir mes y año';
  override startDateLabel = 'Fecha de inicio';
  override endDateLabel = 'Fecha de término';
  override comparisonDateLabel = 'Fechas de comparación';
}

// Selector de fecha con calendario en español: 01/12/2026, semana desde el lunes.
// Va en los providers de cada componente que lo usa (no en app.config) para que date-fns no entre al bundle inicial
export const PROVEEDORES_FECHA: Provider[] = [
  provideDateFnsAdapter(),
  { provide: MAT_DATE_LOCALE, useValue: es },
  { provide: MatDatepickerIntl, useClass: CalendarioEnEspanol },
];

// El calendario entrega un Date y los formularios parten con texto "2026-12-01": a la API siempre va como texto
export function fechaIso(valor: Date | string | null | undefined): string {
  if (!valor) return '';
  if (typeof valor === 'string') return valor;
  return isValid(valor) ? format(valor, 'yyyy-MM-dd') : '';
}
