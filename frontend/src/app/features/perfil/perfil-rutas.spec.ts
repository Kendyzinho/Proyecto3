import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatMenuHarness } from '@angular/material/menu/testing';
import { Router, provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { routes } from '../../app.routes';
import { authInterceptor } from '../../core/interceptors/auth-interceptor';
import { Auth } from '../../core/services/auth';

describe('Navegación del perfil', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideRouter(routes), provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()],
    });
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
    localStorage.clear();
  });

  it('protege el acceso directo sin sesión', async () => {
    await RouterTestingHarness.create('/perfil');
    expect(TestBed.inject(Router).url).toBe('/auth');
    TestBed.inject(HttpTestingController).expectNone('/api/me');
  });

  it.each([
    ['estudiante', '/estudiante', 'Estudiante'], ['coordinadora', '/coordinacion', 'Coordinadora de Prácticas'],
    ['secretaria', '/secretaria', 'Secretaria de Jefatura'], ['jefe', '/jefatura', 'Jefe de Carrera'],
  ])('integra Mi perfil y volver al inicio para %s', async (rol, inicio, nombreRol) => {
    const usuario = { id: 1, name: 'Usuario de prueba', email: 'prueba@uta.cl', rut: null, rol };
    localStorage.setItem('token', 'token-perfil');
    localStorage.setItem('usuario', JSON.stringify(usuario));
    const harness = await RouterTestingHarness.create(inicio);
    const menu = await TestbedHarnessEnvironment.loader(harness.fixture).getHarness(MatMenuHarness);
    await menu.open();
    const [perfil] = await menu.getItems({ text: /Mi perfil/ });
    await perfil.click();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/me').flush({ data: usuario });
    await harness.fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe('/perfil');
    expect(harness.routeNativeElement?.querySelector('dl')?.textContent).toContain(nombreRol);
    expect(harness.routeNativeElement?.querySelectorAll('app-menu-usuario')).toHaveLength(1);

    harness.routeNativeElement!.querySelector<HTMLAnchorElement>(`a[href="${inicio}"]`)!.click();
    await harness.fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe(inicio);

    await harness.navigateByUrl('/perfil');
    http.expectOne('/api/me').flush({ data: usuario });
    TestBed.inject(Auth).logout();
    http.expectOne('/api/logout').flush(null);
    await harness.fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe('/auth');
    await harness.navigateByUrl('/perfil');
    expect(TestBed.inject(Router).url).toBe('/auth');
  });
});
