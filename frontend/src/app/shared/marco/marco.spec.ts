import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Marco, iniciales } from './marco';

describe('Marco', () => {
  beforeEach(() => {
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Marta Rojas', email: 'secretaria@uta.cl', rol: 'secretaria' }));
  });

  afterEach(() => localStorage.clear());

  it('muestra el usuario, su rol y las opciones del menú', async () => {
    await TestBed.configureTestingModule({
      imports: [Marco],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(Marco);
    fixture.componentRef.setInput('grupo', 'Secretaría de Jefatura');
    fixture.componentRef.setInput('menu', [{ texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' }]);
    fixture.detectChanges();
    await fixture.whenStable();

    const texto = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(texto).toContain('Marta Rojas');
    expect(texto).toContain('Secretaria de Jefatura');
    expect(texto).toContain('Nómina de estudiantes');
  });

  it('arma las iniciales con las dos primeras palabras', () => {
    expect(iniciales('Ignacia Muñoz Vega')).toBe('IM');
    expect(iniciales('')).toBe('');
  });
});
