import { TestBed } from '@angular/core/testing';
import { Route, Router, UrlTree, provideRouter } from '@angular/router';

import { Usuario } from '../models/usuario';
import { authGuard } from './auth-guard';

describe('authGuard', () => {
  const ejecutar = (route: Route) => TestBed.runInInjectionContext(() => authGuard(route, []));

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideRouter([])] });
  });

  it('sin sesión manda al login', () => {
    const resultado = ejecutar({ data: { rol: 'coordinadora' } }) as UrlTree;
    expect(TestBed.inject(Router).serializeUrl(resultado)).toBe('/auth');
  });

  it('con otro rol manda al inicio de su propio rol', () => {
    const estudiante: Usuario = { id: 4, name: 'Matías', email: 'estudiante@alumnos.uta.cl', rol: 'estudiante' };
    localStorage.setItem('usuario', JSON.stringify(estudiante));

    const resultado = ejecutar({ data: { rol: 'coordinadora' } }) as UrlTree;
    expect(TestBed.inject(Router).serializeUrl(resultado)).toBe('/estudiante');
  });
});
