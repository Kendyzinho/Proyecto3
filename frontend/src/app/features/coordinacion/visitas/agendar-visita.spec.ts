import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FormGroup } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';

import { AgendarVisita, DatosDialogoVisita } from './agendar-visita';
import { PracticaDisponible, Visita } from './visitas-api';

const PRODUCTORA = { id: 2, nombre: 'Productora Norte Visual' };
const MUNICIPALIDAD = { id: 1, nombre: 'Municipalidad de Arica' };
const DISPONIBLES: PracticaDisponible[] = [
  { id: 11, estudiante: 'Antonia Vega', tipo_practica: 'Laboral II', centro: PRODUCTORA },
  { id: 12, estudiante: 'Pedro Lagos', tipo_practica: 'Profesional', centro: PRODUCTORA },
  { id: 13, estudiante: 'Camila Rojas', tipo_practica: 'Laboral I', centro: MUNICIPALIDAD },
];

describe('AgendarVisita', () => {
  let fixture: ComponentFixture<AgendarVisita>;
  let http: HttpTestingController;
  let referencia: { close: ReturnType<typeof vi.fn>; disableClose: boolean };
  const componente = () => fixture.componentInstance as unknown as { form: FormGroup; practicasDelCentro: () => { id: number }[] };
  const guardar = () => fixture.nativeElement.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));

  function crear(datos: DatosDialogoVisita) {
    referencia = { close: vi.fn(), disableClose: false };
    TestBed.configureTestingModule({
      imports: [AgendarVisita],
      providers: [provideHttpClient(), provideHttpClientTesting(), { provide: MAT_DIALOG_DATA, useValue: datos }, { provide: MatDialogRef, useValue: referencia }],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(AgendarVisita);
    fixture.detectChanges();
    http.expectOne('/api/coordinacion/visitas/practicas-disponibles').flush({ data: DISPONIBLES });
    fixture.detectChanges();
  }
  afterEach(() => http.verify());

  it('muestra solo las prácticas del centro elegido y limpia la selección al cambiar de centro', () => {
    crear({ centroId: 2, practicas: [12] });
    expect(componente().practicasDelCentro().map((p) => p.id)).toEqual([11, 12]);
    expect(componente().form.controls['practicas'].value).toEqual([12]);

    componente().form.controls['centro'].setValue(1);
    expect(componente().practicasDelCentro().map((p) => p.id)).toEqual([13]);
    expect(componente().form.controls['practicas'].value).toEqual([]);
  });

  it('no envía sin práctica, fecha ni hora', () => {
    crear({});
    componente().form.patchValue({ centro: 2 });
    guardar();
    expect(componente().form.invalid).toBe(true);
    http.expectNone('/api/coordinacion/visitas');
  });

  it('envía la fecha y la hora como texto local', () => {
    crear({ centroId: 2, practicas: [11, 12] });
    componente().form.patchValue({ fecha: new Date(2026, 11, 2), hora: '10:30', modalidad: 'virtual' });
    guardar();

    const peticion = http.expectOne('/api/coordinacion/visitas');
    expect(peticion.request.method).toBe('POST');
    expect(peticion.request.body).toEqual({ fecha_hora: '2026-12-02 10:30', modalidad: 'virtual', practicas: [11, 12] });
    peticion.flush({ data: { id: 5 } });
    expect(referencia.close).toHaveBeenCalledWith({ id: 5 });
  });

  it('al editar parte con los datos de la visita y la actualiza', () => {
    const visita: Visita = {
      id: 7, fecha_hora: '2026-12-03 15:00', modalidad: 'terreno', estado: 'agendada', centro: PRODUCTORA,
      practicas: [{ id: 12, estudiante: 'Pedro Lagos', tipo_practica: 'Profesional' }], pautas: { enviadas: 0, registradas: 0, total: 1 },
    };
    crear({ visita });
    expect(componente().form.getRawValue()).toEqual({ centro: 2, practicas: [12], fecha: '2026-12-03', hora: '15:00', modalidad: 'terreno' });

    guardar();
    const peticion = http.expectOne('/api/coordinacion/visitas/7');
    expect(peticion.request.method).toBe('PUT');
    expect(peticion.request.body).toEqual({ fecha_hora: '2026-12-03 15:00', modalidad: 'terreno', practicas: [12] });
    peticion.flush({ data: visita });
  });

  it('muestra el error del servidor y deja volver a intentar', () => {
    crear({ centroId: 2, practicas: [12] });
    componente().form.patchValue({ fecha: '2026-12-02', hora: '10:30' });
    guardar();
    http.expectOne('/api/coordinacion/visitas').flush(
      { errors: { practicas: ['Todas las prácticas de una visita deben ser del mismo centro.'] } },
      { status: 422, statusText: 'Unprocessable' },
    );
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Todas las prácticas de una visita deben ser del mismo centro.');
    expect(referencia.disableClose).toBe(false);
  });
});
