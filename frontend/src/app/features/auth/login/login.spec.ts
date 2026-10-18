import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { Login } from './login';
import { Auth } from '../../../core/services/auth';

describe('Login', () => {
  let fixture: ComponentFixture<Login>;

  beforeEach(async () => {
    localStorage.clear();
    await TestBed.configureTestingModule({
      imports: [Login],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Login);
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    await fixture.whenStable();
  });

  afterEach(() => {
    TestBed.inject(HttpTestingController).verify();
    localStorage.clear();
  });

  it('muestra el error de credenciales cuando la API responde 422', async () => {
    const el = fixture.nativeElement as HTMLElement;
    const correo = el.querySelector<HTMLInputElement>('input[type=email]')!;
    const clave = el.querySelector<HTMLInputElement>('input[type=password]')!;
    correo.value = 'coordinadora@uta.cl';
    correo.dispatchEvent(new Event('input'));
    clave.value = 'mala';
    clave.dispatchEvent(new Event('input'));
    el.querySelector('form')!.dispatchEvent(new Event('submit'));

    TestBed.inject(HttpTestingController).expectOne('/api/login').flush({}, { status: 422, statusText: 'Unprocessable' });
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('[role=alert]')?.textContent).toContain('Correo o contraseña incorrectos');
  });

  it('distingue en pantalla el cierre local de la confirmación pendiente y del error de red', async () => {
    localStorage.setItem('token', 'sesion-anterior');
    TestBed.inject(Auth).logout();
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('[role=status]')?.textContent).toContain('Confirmando');

    TestBed.inject(HttpTestingController).expectOne('/api/logout').error(new ProgressEvent('error'));
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('[role=alert]')?.textContent).toContain('No pudimos confirmar');
    expect(TestBed.inject(Auth).autenticado()).toBe(false);
  });

  it.each([
    ['coordinadora', '/coordinacion'], ['secretaria', '/secretaria'],
    ['jefe', '/jefatura'], ['estudiante', '/estudiante'],
  ])('permite iniciar sesión como %s después del cierre', (rol, inicio) => {
    localStorage.setItem('token', 'sesion-anterior');
    TestBed.inject(Auth).logout();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/logout').flush(null);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('[role=status]')?.textContent).toContain('Sesión cerrada correctamente');

    const el = fixture.nativeElement as HTMLElement;
    const correo = el.querySelector<HTMLInputElement>('input[type=email]')!;
    const clave = el.querySelector<HTMLInputElement>('input[type=password]')!;
    correo.value = 'coordinadora@uta.cl';
    clave.value = 'clave-de-prueba';
    correo.dispatchEvent(new Event('input'));
    clave.dispatchEvent(new Event('input'));
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    http.expectOne('/api/login').flush({ token: 'sesion-nueva', usuario: { id: 2, name: 'Usuario de prueba', email: correo.value, rut: null, rol } });
    expect(TestBed.inject(Auth).autenticado()).toBe(true);
    expect(TestBed.inject(Auth).estadoCierre()).toBe('ninguno');
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenLastCalledWith(inicio);
  });
});
