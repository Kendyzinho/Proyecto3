import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormGroup } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';

import { CalendarioPractica } from './asignacion-practicas.model';
import { FechasHorario } from './fechas-horario';

const SIN_DEFINIR: CalendarioPractica = {
  id: 9, estudiante: 'Camila Rojas', tipo_practica: 'Laboral I', centro: 'Municipalidad de Arica',
  fecha_inicio: null, fecha_fin: null, horario: null,
};
const DEFINIDA: CalendarioPractica = {
  ...SIN_DEFINIR, fecha_inicio: '2026-11-16', fecha_fin: '2026-12-18',
  horario: { dias: [1, 3], hora_desde: '09:00', hora_hasta: '13:00' },
};

describe('FechasHorario', () => {
  let fixture: ComponentFixture<FechasHorario>;
  let http: HttpTestingController;
  let referencia: { close: ReturnType<typeof vi.fn>; disableClose: boolean };
  const form = () => (fixture.componentInstance as unknown as { form: FormGroup }).form;
  const guardar = () => fixture.nativeElement.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

  function crear(datos: CalendarioPractica) {
    referencia = { close: vi.fn(), disableClose: false };
    TestBed.configureTestingModule({
      imports: [FechasHorario],
      providers: [provideHttpClient(), provideHttpClientTesting(), { provide: MAT_DIALOG_DATA, useValue: datos }, { provide: MatDialogRef, useValue: referencia }],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(FechasHorario);
    fixture.detectChanges();
  }
  afterEach(() => http.verify());

  it('parte vacío si falta definirlos y precargado si ya existen', () => {
    crear(DEFINIDA);
    expect(form().getRawValue()).toEqual({ fecha_inicio: '2026-11-16', fecha_fin: '2026-12-18', dias: [1, 3], hora_desde: '09:00', hora_hasta: '13:00' });
  });

  it('muestra los siete días y marca o desmarca al hacer clic', () => {
    crear(SIN_DEFINIR);
    const casillas = [...fixture.nativeElement.querySelectorAll('mat-checkbox')] as HTMLElement[];
    expect(casillas.map((c) => c.textContent?.trim())).toEqual(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']);
    (casillas[4].querySelector('input') as HTMLInputElement).click();
    (casillas[0].querySelector('input') as HTMLInputElement).click();
    expect(form().controls['dias'].value).toEqual([1, 5]);
    (casillas[4].querySelector('input') as HTMLInputElement).click();
    expect(form().controls['dias'].value).toEqual([1]);
  });

  it('no envía sin días, con fechas invertidas ni con horas invertidas', () => {
    crear(DEFINIDA);
    form().patchValue({ dias: [] }); guardar();
    form().patchValue({ dias: [1], fecha_fin: '2026-11-01' }); guardar();
    expect(form().hasError('fechas')).toBe(true);
    form().patchValue({ fecha_fin: '2026-12-18', hora_hasta: '08:00' }); guardar();
    expect(form().hasError('horas')).toBe(true);
    http.expectNone('/api/coordinacion/calendarios/9');
  });

  it('guarda y cierra avisando que cambió', () => {
    crear(DEFINIDA);
    guardar();
    const req = http.expectOne('/api/coordinacion/calendarios/9');
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual({ fecha_inicio: '2026-11-16', fecha_fin: '2026-12-18', dias: [1, 3], hora_desde: '09:00', hora_hasta: '13:00' });
    req.flush({ message: 'ok' });
    expect(referencia.close).toHaveBeenCalledWith(true);
  });

  it('muestra el error del servidor y no cierra', () => {
    crear(DEFINIDA);
    guardar();
    http.expectOne('/api/coordinacion/calendarios/9').flush({ errors: { fecha_fin: ['La fecha de término debe ser igual o posterior al inicio.'] } }, { status: 422, statusText: 'Unprocessable' });
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('La fecha de término debe ser igual');
    expect(referencia.close).not.toHaveBeenCalled();
  });
});
