import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Nomina } from './nomina';
import { Revision, textoPrerrequisito } from './nomina-api';

const CATALOGO = {
  periodos: [{ id: 2, nombre: '2º semestre 2026' }],
  periodo_actual_id: 2,
  practicas: [{ id: 1, nombre: 'Laboral I', prerrequisito: 'Primer año aprobado' }],
};

const REVISION: Revision = {
  resumen: { total: 3, habilitados: 1, bloqueados: 1, con_error: 1 },
  filas: [
    { fila: 2, rut: '21.556.302-2', nombre: 'Ignacia Muñoz Vega', correo: 'ignacia.munoz@alumnos.uta.cl', anio_ingreso: 2025,
      practica: 'Laboral I', prerrequisito: 'Primer año aprobado', cumple: true, resultado: 'habilitado', errores: [] },
    { fila: 3, rut: '21.902.330-8', nombre: 'Benjamín Castro Ríos', correo: 'benjamin.castro@alumnos.uta.cl', anio_ingreso: 2024,
      practica: 'Laboral II', prerrequisito: 'Laboral I aprobada', cumple: false, resultado: 'bloqueado', errores: [] },
    { fila: 4, rut: '21.448.09-2', nombre: 'Tomás Flores Gutiérrez', correo: 'tomas.flores@gmail.com', anio_ingreso: 2025,
      practica: 'Laboral I', prerrequisito: 'Primer año aprobado', cumple: true, resultado: 'error',
      errores: { rut: 'RUT inválido', correo: 'Correo no institucional' } },
  ],
};

describe('Nomina', () => {
  let fixture: ComponentFixture<Nomina>;
  let http: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Nomina],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    fixture = TestBed.createComponent(Nomina);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/nomina/catalogo').flush(CATALOGO);
    http.expectOne((r) => r.url === '/api/nomina' && r.params.get('periodo_id') === '2').flush({ data: [] });
    fixture.detectChanges();
    await fixture.whenStable();
  });

  afterEach(() => http.verify());

  it('sin planilla muestra la nómina vacía del período', () => {
    const texto = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(texto).toContain('Nómina del 2º semestre 2026');
    expect(texto).toContain('Todavía no hay estudiantes');
  });

  it('al subir una planilla muestra el resultado de la revisión', async () => {
    const entrada = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>('input[type=file]')!;
    const archivo = new File(['x'], 'nomina.csv', { type: 'text/csv' });
    Object.defineProperty(entrada, 'files', { value: [archivo] });
    entrada.dispatchEvent(new Event('change'));

    const peticion = http.expectOne('/api/nomina/revisar');
    expect((peticion.request.body as FormData).get('periodo_id')).toBe('2');
    peticion.flush(REVISION);
    fixture.detectChanges();
    await fixture.whenStable();

    const texto = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(texto).toContain('2. Resultado de la revisión');
    expect(texto).toContain('1 habilitado');
    expect(texto).toContain('1 sin prerrequisito');
    expect(texto).toContain('1 fila con error');
    expect(texto).toContain('Laboral I pendiente');
    expect(texto).toContain('Confirmar carga de 2');
  });

  it('cambia "aprobada" por "pendiente" cuando no cumple', () => {
    expect(textoPrerrequisito('Laboral I aprobada', false)).toBe('Laboral I pendiente');
    expect(textoPrerrequisito('Primer año aprobado', true)).toBe('Primer año aprobado');
    expect(textoPrerrequisito(null, true)).toBe('—');
  });
});
