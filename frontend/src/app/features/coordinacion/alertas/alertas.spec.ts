import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Alertas } from './alertas';

describe('Alertas', () => {
  let fixture: ComponentFixture<Alertas>;
  let http: HttpTestingController;
  const alerta = (id: number, tipo: string, atendida = false) => ({
    id, tipo, fecha_referencia: '2026-11-11', fecha_generada: '2026-11-18T06:00:00-03:00',
    atendida, fecha_atencion: atendida ? '2026-11-18T10:00:00-03:00' : null, atendida_por: atendida ? 'Diana Henry' : null,
    practica: { id: 7, estudiante: 'Diego Choque Vargas', correo: 'diego@alumnos.uta.cl', tipo_practica: 'Profesional', centro: 'Productora Norte Visual', supervisor: 'Sergio Paredes' },
  });
  const elemento = (): HTMLElement => fixture.nativeElement;
  const boton = (texto: string) => [...elemento().querySelectorAll('button')].find((b) => b.textContent?.includes(texto)) as HTMLButtonElement;

  beforeEach(() => {
    TestBed.configureTestingModule({
      imports: [Alertas],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(Alertas);
    fixture.detectChanges();
    http.expectOne('/api/coordinacion/alertas?estado=abiertas')
      .flush({ data: [alerta(1, 'abandono'), alerta(2, 'sin_confirmar')], meta: { abiertas: 2, atendidas: 1 } });
    fixture.detectChanges();
  });

  afterEach(() => http.verify());

  it('muestra cada alerta con su luz, el motivo y el estudiante', () => {
    expect(elemento().querySelector('.luz--roja')).not.toBeNull();
    expect(elemento().querySelector('.luz--amarilla')).not.toBeNull();
    expect(elemento().textContent).toContain('Posible abandono');
    expect(elemento().textContent).toContain('3 días esperados seguidos sin marca desde el 11-11-2026');
    expect(elemento().textContent).toContain('Diego Choque Vargas');
    expect(elemento().textContent).toContain('Abiertas (2)');
  });

  it('marcar como atendida avisa al backend y recarga la lista', () => {
    boton('Marcar como atendida').click();
    http.expectOne({ method: 'POST', url: '/api/coordinacion/alertas/1/atender' }).flush({ data: alerta(1, 'abandono', true) });
    http.expectOne('/api/coordinacion/alertas?estado=abiertas').flush({ data: [alerta(2, 'sin_confirmar')], meta: { abiertas: 1, atendidas: 2 } });
    fixture.detectChanges();

    expect(elemento().textContent).not.toContain('Posible abandono');
    expect(elemento().textContent).toContain('Abiertas (1)');
  });

  it('el filtro de atendidas muestra quién la atendió', () => {
    boton('Atendidas').click();
    http.expectOne('/api/coordinacion/alertas?estado=atendidas').flush({ data: [alerta(3, 'inasistencia', true)], meta: { abiertas: 2, atendidas: 1 } });
    fixture.detectChanges();

    expect(elemento().textContent).toContain('Atendida por Diana Henry');
    expect(boton('Marcar como atendida')).toBeUndefined();
  });
});
