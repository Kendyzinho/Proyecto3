import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';

import { Informes } from './informes';
import { FilaInforme } from './informes-api';

const FILAS: FilaInforme[] = [
  { id: 11, estudiante: 'Antonia Vega', tipo_practica: 'Laboral II', centro: 'Productora Norte Visual', supervisor: 'Sergio Paredes', fecha_fin: '2026-10-02', estado: 'sin_solicitar', solicitado_en: null, enviado_en: null },
  { id: 12, estudiante: 'Pedro Lagos', tipo_practica: 'Profesional', centro: 'Productora Norte Visual', supervisor: 'Sergio Paredes', fecha_fin: '2026-11-27', estado: 'borrador', solicitado_en: '2026-11-20T10:00:00-03:00', enviado_en: null },
  { id: 13, estudiante: 'Camila Rojas', tipo_practica: 'Laboral I', centro: 'Municipalidad de Arica', supervisor: 'Paula Contreras', fecha_fin: '2026-09-04', estado: 'enviado', solicitado_en: '2026-11-10T10:00:00-03:00', enviado_en: '2026-11-12T10:00:00-03:00' },
];

describe('Informes', () => {
  let fixture: ComponentFixture<Informes>;
  let http: HttpTestingController;
  const filas = () => [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row')] as HTMLElement[];
  const boton = (fila: HTMLElement) => fila.querySelector('button') as HTMLButtonElement;

  beforeEach(() => {
    TestBed.configureTestingModule({ imports: [Informes], providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(Informes);
    http.expectOne('/api/coordinacion/informes').flush({ data: FILAS });
    fixture.detectChanges();
  });

  afterEach(() => {
    http.verify();
    vi.restoreAllMocks();
  });

  it('muestra cada práctica con su estado y la acción que corresponde', () => {
    expect(filas()).toHaveLength(3);
    expect(filas()[0].textContent).toContain('Sin solicitar');
    expect(boton(filas()[0]).textContent).toContain('Solicitar informe');
    expect(filas()[1].textContent).toContain('En borrador');
    expect(boton(filas()[1]).textContent).toContain('Reenviar aviso');
    expect(filas()[2].textContent).toContain('Enviado');
    expect(boton(filas()[2]).textContent).toContain('Ver informe');
  });

  it('solicitar pide confirmación, llama a la API y actualiza la fila', () => {
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(true);

    boton(filas()[0]).click();
    expect(confirmar).toHaveBeenCalledWith('¿Pedirle a Sergio Paredes el informe confidencial de Antonia Vega?');
    const peticion = http.expectOne('/api/coordinacion/informes/11/solicitar');
    expect(peticion.request.method).toBe('POST');
    peticion.flush({ data: { ...FILAS[0], estado: 'solicitado' }, message: 'Le pedimos el informe a Sergio Paredes.' });
    fixture.detectChanges();

    expect(filas()[0].textContent).toContain('Solicitado');
    expect(boton(filas()[0]).textContent).toContain('Reenviar aviso');
  });

  it('si no confirma, no solicita', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    boton(filas()[0]).click();
    http.expectNone('/api/coordinacion/informes/11/solicitar');
  });

  it('ver informe abre el informe enviado', () => {
    const navegar = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);

    boton(filas()[2]).click();

    expect(navegar).toHaveBeenCalledWith([13], expect.anything());
  });
});
