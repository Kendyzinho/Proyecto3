import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatMenuHarness } from '@angular/material/menu/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { authInterceptor } from '../../core/interceptors/auth-interceptor';
import { Auth } from '../../core/services/auth';
import { MenuUsuario } from './menu-usuario';

describe('MenuUsuario', () => {
  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem('token', 'sesion-menu');
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Matías Herrera', email: 'estudiante@alumnos.uta.cl', rol: 'estudiante' }));
    TestBed.configureTestingModule({
      imports: [MenuUsuario],
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    });
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
    localStorage.clear();
  });

  it('ofrece cerrar sesión desde el menú y borra los datos visibles al pulsar', async () => {
    const fixture = TestBed.createComponent(MenuUsuario);
    const menu = await TestbedHarnessEnvironment.loader(fixture).getHarness(MatMenuHarness);
    await menu.open();
    const [cerrar] = await menu.getItems({ text: /Cerrar sesión/ });
    expect(cerrar).toBeDefined();
    await cerrar.click();

    const peticion = TestBed.inject(HttpTestingController).expectOne('/api/logout');
    expect(peticion.request.headers.get('Authorization')).toBe('Bearer sesion-menu');
    expect(TestBed.inject(Auth).usuario()).toBeNull();
    expect(fixture.nativeElement.textContent).not.toContain('Matías');
    peticion.flush(null, { status: 204, statusText: 'No Content' });
  });

  it('conserva Mi perfil y muestra el panel con clase visual propia', async () => {
    const fixture = TestBed.createComponent(MenuUsuario);
    const menu = await TestbedHarnessEnvironment.loader(fixture).getHarness(MatMenuHarness);
    await menu.open();

    expect(document.body.querySelector('.menu-usuario-panel')).not.toBeNull();
    const [perfil] = await menu.getItems({ text: /Mi perfil/ });
    await perfil.click();
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalled();
  });

  it.each([
    ['estudiante', 'Estudiante'],
    ['coordinadora', 'Coordinadora de Prácticas'],
    ['secretaria', 'Secretaria de Jefatura'],
    ['jefe', 'Jefe de Carrera'],
  ])('muestra únicamente las opciones del menú para rol %s', async (rol, nombreRol) => {
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Matías Herrera', email: 'usuario@uta.cl', rol }));
    const fixture = TestBed.createComponent(MenuUsuario);
    const menu = await TestbedHarnessEnvironment.loader(fixture).getHarness(MatMenuHarness);
    await menu.open();

    const panel = document.body.querySelector('.menu-usuario-panel');
    expect(panel?.querySelector('.datos')).toBeNull();
    expect(panel?.querySelector('mat-divider')).toBeNull();
    expect(panel?.textContent).not.toContain('Matías Herrera');
    expect(panel?.textContent).not.toContain(nombreRol);
    expect(await menu.getItems()).toHaveLength(2);
  });
});
