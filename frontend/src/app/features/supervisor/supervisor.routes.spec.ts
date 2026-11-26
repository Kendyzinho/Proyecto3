import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { routes } from '../../app.routes';

describe('rutas del supervisor(a)', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter(routes)] });
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).match(() => true);
    localStorage.clear();
  });

  it('los informes piden sesión', async () => {
    await RouterTestingHarness.create('/supervisor');
    expect(TestBed.inject(Router).url).toBe('/auth');
  });

  it('otro rol no entra a los informes del supervisor', async () => {
    localStorage.setItem('token', 't');
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Usuario', email: 'e@alumnos.uta.cl', rol: 'estudiante' }));
    await RouterTestingHarness.create('/supervisor/informes/5');
    expect(TestBed.inject(Router).url).toBe('/estudiante');
  });

  it('el supervisor entra a sus informes', async () => {
    localStorage.setItem('token', 't');
    localStorage.setItem('usuario', JSON.stringify({ id: 9, name: 'Sergio', email: 's@example.test', rol: 'supervisor' }));
    const harness = await RouterTestingHarness.create('/supervisor');
    expect(TestBed.inject(Router).url).toBe('/supervisor');
    TestBed.inject(HttpTestingController).expectOne('/api/supervisor/informes').flush({ data: [] });
    await harness.fixture.whenStable();
    expect(harness.routeNativeElement?.textContent).toContain('No tienes informes por responder.');
  });

  it('el enlace de asistencia de HU-26 sigue sin pedir sesión', async () => {
    await RouterTestingHarness.create('/supervisor/asistencia/3/2026-11-16?expires=1&signature=x');
    expect(TestBed.inject(Router).url).toContain('/supervisor/asistencia/3/2026-11-16');
  });
});
