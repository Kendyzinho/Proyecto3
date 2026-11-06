import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { authInterceptor } from '../../core/interceptors/auth-interceptor';
import { Usuario } from '../../core/models/usuario';
import { Auth } from '../../core/services/auth';
import { Perfil } from './perfil';

describe('Perfil', () => {
  let fixture: ComponentFixture<Perfil>;
  let http: HttpTestingController;
  let el: HTMLElement;
  const usuario: Usuario = { id: 4, name: 'Persona desde API', rut: '11.111.111-1', email: 'perfil@alumnos.uta.cl', rol: 'estudiante' };

  beforeEach(async () => {
    localStorage.clear();
    sessionStorage.clear();
    localStorage.setItem('token', 'token-perfil');
    localStorage.setItem('usuario', JSON.stringify({ ...usuario, name: 'Nombre almacenado' }));
    await TestBed.configureTestingModule({
      imports: [Perfil],
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(Perfil);
    fixture.detectChanges();
    el = fixture.nativeElement;
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
    sessionStorage.clear();
  });

  const cargar = (datos: Usuario | null = usuario) => {
    const peticion = http.expectOne('/api/me');
    expect(peticion.request.headers.get('Authorization')).toBe('Bearer token-perfil');
    peticion.flush({ data: datos });
    fixture.detectChanges();
  };

  const rellenar = (actual = 'Anterior123', nueva = 'NuevaClave2026', confirmacion = nueva) => {
    for (const [id, valor] of Object.entries({ current_password: actual, password: nueva, password_confirmation: confirmacion })) {
      const input = el.querySelector<HTMLInputElement>('#' + id)!;
      input.value = valor;
      input.dispatchEvent(new Event('input'));
    }
    fixture.detectChanges();
  };

  const enviar = () => {
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();
  };

  it('consulta el backend y presenta los cuatro datos con estado de carga', () => {
    expect(el.textContent).toContain('Cargando tus datos');
    cargar();
    const datos = el.querySelector('dl')!;
    expect(datos.textContent).toContain(usuario.name);
    expect(datos.textContent).toContain(usuario.rut);
    expect(datos.textContent).toContain(usuario.email);
    expect(datos.textContent).toContain('Estudiante');
    expect(datos.textContent).not.toContain('Nombre almacenado');
    expect(datos.querySelectorAll('dt')).toHaveLength(4);
  });

  it('presenta un RUT nulo como No registrado', () => {
    cargar({ ...usuario, rut: null });
    expect(el.querySelector('dl')?.textContent).toContain('No registrado');
  });

  it('maneja un perfil ausente sin inventar datos', () => {
    cargar(null);
    expect(el.textContent).toContain('No hay datos de perfil disponibles');
    expect(el.querySelector<HTMLButtonElement>('button[type=submit]')!.disabled).toBe(true);
    expect(el.querySelector('dl')).toBeNull();
  });

  it('muestra un error de carga y permite reintentar', () => {
    http.expectOne('/api/me').error(new ProgressEvent('error'));
    fixture.detectChanges();
    expect(el.querySelector('[role=alert]')?.textContent).toContain('No pudimos cargar');
    Array.from(el.querySelectorAll('button')).find((b) => b.textContent?.includes('Reintentar'))!.click();
    cargar();
    expect(el.querySelector('dl')?.textContent).toContain(usuario.name);
  });

  it('exige los tres campos antes de enviar', () => {
    cargar();
    enviar();
    http.expectNone('/api/me/password');
    expect(el.querySelectorAll('mat-error').length).toBe(3);
  });

  it.each([
    ['Ab1', 'al menos 8'], ['nuevaclave2026', 'mayúscula'], ['NuevaClave', 'número'],
    ['A1' + 'á'.repeat(36), 'demasiado larga'], ['Anterior123', 'distinta de la actual'],
  ])('rechaza la nueva clave inválida %s', (clave, mensaje) => {
    cargar();
    rellenar('Anterior123', clave);
    enviar();
    http.expectNone('/api/me/password');
    expect(el.textContent).toContain(mensaje);
  });

  it('muestra la confirmación diferente en su campo', () => {
    cargar();
    rellenar('Anterior123', 'NuevaClave2026', 'Distinta2026');
    enviar();
    http.expectNone('/api/me/password');
    expect(el.textContent).toContain('Las contraseñas no coinciden');
    expect(el.querySelector('#password_confirmation')?.getAttribute('aria-invalid')).toBe('true');
  });

  it('mantiene visible el error de contraseña actual devuelto por Laravel', () => {
    cargar();
    rellenar();
    enviar();
    http.expectOne('/api/me/password').flush({ errors: { current_password: ['La contraseña actual no es correcta.'] } },
      { status: 422, statusText: 'Unprocessable Entity' });
    fixture.detectChanges();
    expect(el.textContent).toContain('La contraseña actual no es correcta.');
    expect(el.querySelector('#current_password')?.getAttribute('aria-invalid')).toBe('true');
    expect(el.querySelector<HTMLButtonElement>('button[type=submit]')!.disabled).toBe(false);
    expect(TestBed.inject(Auth).autenticado()).toBe(true);
  });

  it('guarda una sola vez, limpia las claves y conserva la sesión', () => {
    cargar();
    rellenar();
    const almacenamiento = { ...localStorage };
    enviar();
    enviar();
    const peticion = http.expectOne('/api/me/password');
    expect(peticion.request.method).toBe('PUT');
    expect(peticion.request.headers.get('Authorization')).toBe('Bearer token-perfil');
    expect(peticion.request.body).toEqual({ current_password: 'Anterior123', password: 'NuevaClave2026', password_confirmation: 'NuevaClave2026' });
    expect(el.querySelector<HTMLButtonElement>('button[type=submit]')!.disabled).toBe(true);
    expect(el.textContent).toContain('Guardando…');
    peticion.flush(null, { status: 204, statusText: 'No Content' });
    fixture.detectChanges();
    expect(el.querySelector('[role=status]')?.textContent).toContain('Contraseña actualizada');
    for (const input of el.querySelectorAll<HTMLInputElement>('input')) {
      expect(input.value).toBe('');
      expect(input.type).toBe('password');
    }
    expect({ ...localStorage }).toEqual(almacenamiento);
    expect(sessionStorage.length).toBe(0);
    expect(TestBed.inject(Auth).token).toBe('token-perfil');
    expect(TestBed.inject(Router).navigateByUrl).not.toHaveBeenCalled();
  });

  it.each([0, 429, 503])('permite recuperarse del error %i sin afirmar éxito', (status) => {
    cargar();
    rellenar();
    enviar();
    const peticion = http.expectOne('/api/me/password');
    if (status === 0) peticion.error(new ProgressEvent('error'));
    else peticion.flush({}, { status, statusText: 'Error' });
    fixture.detectChanges();
    expect(el.querySelector('[role=alert]')?.textContent).toContain(status === 429 ? 'Demasiados intentos' : 'No pudimos confirmar');
    expect(el.querySelector<HTMLButtonElement>('button[type=submit]')!.disabled).toBe(false);
    expect(el.textContent).not.toContain('Contraseña actualizada');
  });

  it('permite mostrar y ocultar una clave y la vuelve a ocultar al guardar', () => {
    cargar();
    rellenar();
    el.querySelector<HTMLButtonElement>('[aria-label="Mostrar nueva contraseña"]')!.click();
    fixture.detectChanges();
    expect(el.querySelector<HTMLInputElement>('#password')!.type).toBe('text');
    enviar();
    http.expectOne('/api/me/password').flush(null);
    fixture.detectChanges();
    expect(el.querySelector<HTMLInputElement>('#password')!.type).toBe('password');
  });

  it('cancela el guardado pendiente al cerrar sesión con US-03', () => {
    cargar();
    rellenar();
    enviar();
    const cambio = http.expectOne('/api/me/password');
    TestBed.inject(Auth).logout();
    expect(cambio.cancelled).toBe(true);
    expect(TestBed.inject(Auth).usuario()).toBeNull();
    http.expectOne('/api/logout').flush(null);
    fixture.destroy();
  });
});
