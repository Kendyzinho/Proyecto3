import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatMenuHarness } from '@angular/material/menu/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { authInterceptor } from '../../core/interceptors/auth-interceptor';
import { Auth } from '../../core/services/auth';

import { Marco, iniciales } from './marco';

describe('Marco', () => {
  beforeEach(() => {
    localStorage.setItem('token', 'sesion-marco');
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

  it('reutiliza un único menú y cierra la sesión desde el layout de Secretaría', async () => {
    localStorage.setItem('token', 'sesion-marco');
    await TestBed.configureTestingModule({
      imports: [Marco],
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();
    const router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    const fixture = TestBed.createComponent(Marco);
    fixture.componentRef.setInput('grupo', 'Secretaría de Jefatura');
    fixture.componentRef.setInput('menu', []);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelectorAll('app-menu-usuario')).toHaveLength(1);
    const menus = await TestbedHarnessEnvironment.loader(fixture).getAllHarnesses(MatMenuHarness);
    expect(menus).toHaveLength(1);
    await menus[0].open();
    const [cerrar] = await menus[0].getItems({ text: /Cerrar sesión/ });
    await cerrar.click();
    expect(TestBed.inject(Auth).usuario()).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/auth', { replaceUrl: true });
    const http = TestBed.inject(HttpTestingController);
    const solicitud = http.expectOne('/api/logout');
    expect(solicitud.request.headers.get('Authorization')).toBe('Bearer sesion-marco');
    solicitud.flush(null, { status: 204, statusText: 'No Content' });
    http.verify();
  });
});
