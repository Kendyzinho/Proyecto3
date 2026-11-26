import { TestBed } from '@angular/core/testing';
import { DateAdapter, MAT_DATE_FORMATS, MatDateFormats } from '@angular/material/core';

import { PROVEEDORES_FECHA, fechaIso } from './fechas';

describe('Calendario en español', () => {
  let adaptador: DateAdapter<Date>;
  let formatos: MatDateFormats;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: PROVEEDORES_FECHA });
    adaptador = TestBed.inject(DateAdapter<Date>);
    formatos = TestBed.inject(MAT_DATE_FORMATS);
  });

  it('lee y muestra día/mes/año, no mes/día', () => {
    const fecha = adaptador.parse('01/12/2026', formatos.parse.dateInput)!;

    expect(fechaIso(fecha)).toBe('2026-12-01');
    expect(adaptador.format(fecha, formatos.display.dateInput)).toBe('01/12/2026');
  });

  it('parte la semana el lunes y nombra los meses en español', () => {
    expect(adaptador.getFirstDayOfWeek()).toBe(1);
    expect(adaptador.getMonthNames('long')[11]).toBe('diciembre');
  });

  it('convierte a texto lo que llega del calendario o del formulario', () => {
    expect(fechaIso(new Date(2026, 11, 1))).toBe('2026-12-01');
    expect(fechaIso('2026-12-01')).toBe('2026-12-01');
    expect(fechaIso(null)).toBe('');
    expect(fechaIso(new Date('no es fecha'))).toBe('');
  });
});
