import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatMenuHarness } from '@angular/material/menu/testing';
import { Router, provideRouter, withComponentInputBinding } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

import { routes } from '../../app.routes';
import { authInterceptor } from '../../core/interceptors/auth-interceptor';
import { Auth } from '../../core/services/auth';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

describe('Navegación del perfil', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideRouter(routes, withComponentInputBinding()), provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting()],
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
    const http = TestBed.inject(HttpTestingController);
    const cargarInicio = () => {
      if (rol === 'coordinadora') {
        http.expectOne('/api/coordinacion/metricas').flush({
          periodo: { id: 2, nombre: '2º semestre 2026' }, practicas_registradas: 5,
        });
      }
      if (rol === 'secretaria') {
        http.expectOne('/api/nomina/catalogo').flush({
          periodos: [{ id: 1, nombre: '2º semestre 2026' }], periodo_actual_id: 1, practicas: [],
        });
        http.expectOne('/api/nomina?periodo_id=1').flush({ data: [] });
        http.expectOne('/api/documentos?periodo_id=1').flush({ data: [] });
      }
    };
    cargarInicio();
    expect(harness.routeNativeElement?.querySelectorAll('app-menu-usuario')).toHaveLength(1);
    const menu = await TestbedHarnessEnvironment.loader(harness.fixture).getHarness(MatMenuHarness);
    await menu.open();
    const [perfil] = await menu.getItems({ text: /Mi perfil/ });
    await perfil.click();
    http.expectOne('/api/me').flush({ data: usuario });
    await harness.fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe('/perfil');
    expect(harness.routeNativeElement?.querySelector('dl')?.textContent).toContain(nombreRol);
    expect(harness.routeNativeElement?.querySelectorAll('app-menu-usuario')).toHaveLength(1);
    expect(harness.routeNativeElement?.querySelectorAll('header.barra')).toHaveLength(1);
    expect(harness.routeNativeElement?.querySelector('nav.lateral__menu')?.textContent).toContain('Inicio');
    expect(harness.routeNativeElement?.querySelector('.lateral__grupo')?.textContent).toContain(nombreRol);
    const opciones = Array.from(harness.routeNativeElement!.querySelectorAll<HTMLAnchorElement>('nav.lateral__menu .opcion'));
    expect(opciones.map((opcion) => ({ texto: opcion.querySelector('span')?.textContent?.trim(), ruta: opcion.getAttribute('href') })))
      .toEqual(MENUS_POR_ROL[rol as keyof typeof MENUS_POR_ROL].map(({ texto, ruta }) => ({ texto, ruta })));

    harness.routeNativeElement!.querySelector<HTMLAnchorElement>(`a[href="${inicio}"]`)!.click();
    await harness.fixture.whenStable();
    expect(TestBed.inject(Router).url).toBe(inicio);
    cargarInicio();

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
