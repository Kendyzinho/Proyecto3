import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Usuarios } from './usuarios';
import { Cuenta } from './usuarios-api';

const CUENTAS: Cuenta[] = [
  { id: 1, rut: null, name: 'Marta Rojas', email: 'secretaria@uta.cl', rol: 'secretaria',
    anio_ingreso: null, activo: true, fecha_baja: null, motivo_baja: null },
  { id: 2, rut: '21.334.120-8', name: 'Camila Rojas Pizarro', email: 'camila.rojas@alumnos.uta.cl', rol: 'estudiante',
    anio_ingreso: 2023, activo: true, fecha_baja: null, motivo_baja: null },
  { id: 3, rut: '19.776.203-5', name: 'Felipe Araya Soto', email: 'felipe.araya@alumnos.uta.cl', rol: 'estudiante',
    anio_ingreso: 2019, activo: false, fecha_baja: '2026-03-01', motivo_baja: 'Egresado' },
];

describe('Usuarios', () => {
  let fixture: ComponentFixture<Usuarios>;
  let http: HttpTestingController;

  beforeEach(async () => {
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Marta Rojas', email: 'secretaria@uta.cl', rol: 'secretaria' }));
    await TestBed.configureTestingModule({
      imports: [Usuarios],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Usuarios);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/usuarios').flush({ data: CUENTAS });
    fixture.detectChanges();
    await fixture.whenStable();
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  function filas(): number {
    return (fixture.nativeElement as HTMLElement).querySelectorAll('tr.mat-mdc-row').length;
  }

  it('muestra todas las cuentas con su rol y estado', () => {
    const texto = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(filas()).toBe(3);
    expect(texto).toContain('Secretaria de Jefatura');
    expect(texto).toContain('Desactivada');
  });

  it('filtra las desactivadas', async () => {
    const botones = Array.from((fixture.nativeElement as HTMLElement).querySelectorAll<HTMLButtonElement>('.filtro'));
    botones.find((b) => b.textContent?.includes('Desactivados'))!.click();
    fixture.detectChanges();
    await fixture.whenStable();
    expect(filas()).toBe(1);
  });
});
