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
});
