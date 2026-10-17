import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { Route, Router, UrlTree, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { Usuario } from '../models/usuario';
import { authGuard, invitadoGuard } from './auth-guard';
import { Auth } from '../services/auth';
import { routes } from '../../app.routes';

describe('authGuard', () => {
  const ejecutar = (route: Route) => TestBed.runInInjectionContext(() => authGuard(route, []));

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter(routes)] });
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
    localStorage.clear();
  });

  it('sin sesión manda al login', () => {
    const resultado = ejecutar({ data: { rol: 'coordinadora' } }) as UrlTree;
    expect(TestBed.inject(Router).serializeUrl(resultado)).toBe('/auth');
  });

  it('con otro rol manda al inicio de su propio rol', () => {
    const estudiante: Usuario = { id: 4, name: 'Matías', email: 'estudiante@alumnos.uta.cl', rol: 'estudiante' };
    localStorage.setItem('usuario', JSON.stringify(estudiante));
    localStorage.setItem('token', 'token-de-prueba');

    const resultado = ejecutar({ data: { rol: 'coordinadora' } }) as UrlTree;
    expect(TestBed.inject(Router).serializeUrl(resultado)).toBe('/estudiante');
  });

  it.each([
    ['coordinadora', '/coordinacion'], ['secretaria', '/secretaria'],
    ['jefe', '/jefatura'], ['estudiante', '/estudiante'],
  ])('bloquea volver al inicio de %s después del logout', async (rol, ruta) => {
    localStorage.setItem('token', 'token-de-prueba');
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Usuario', email: 'usuario@uta.cl', rol }));
    const harness = await RouterTestingHarness.create(ruta);
    expect(harness.routeNativeElement?.textContent).toContain('Sesión iniciada');
    expect(harness.routeNativeElement?.querySelector('[aria-label="Menú de usuario"]')).not.toBeNull();

    TestBed.inject(Auth).logout();
    TestBed.inject(HttpTestingController).expectOne('/api/logout').flush(null);
    await harness.fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe('/auth');

    await harness.navigateByUrl(ruta);
    expect(TestBed.inject(Router).url).toBe('/auth');
    expect(harness.routeNativeElement?.textContent).toContain('Iniciar sesión');
    expect(harness.routeNativeElement?.textContent).not.toContain('Sesión iniciada');
  });

  it('no acepta un usuario guardado sin token', () => {
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Usuario', rol: 'jefe' }));
    const resultado = ejecutar({ data: { rol: 'jefe' } }) as UrlTree;
    expect(TestBed.inject(Router).serializeUrl(resultado)).toBe('/auth');
  });

  it('no crea un ciclo de navegación si el token desaparece antes que el usuario en memoria', () => {
    localStorage.setItem('token', 'token-de-prueba');
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Usuario', rol: 'jefe' }));
    TestBed.inject(Auth);
    localStorage.removeItem('token');
    expect(TestBed.runInInjectionContext(() => invitadoGuard({}, []))).toBe(true);
    expect(TestBed.inject(Router).serializeUrl(ejecutar({ data: { rol: 'jefe' } }) as UrlTree)).toBe('/auth');
  });
});
