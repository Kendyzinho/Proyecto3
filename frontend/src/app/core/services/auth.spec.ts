import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { authInterceptor } from '../interceptors/auth-interceptor';
import { Auth } from './auth';

describe('Auth', () => {
  let auth: Auth;
  let http: HttpTestingController;
  const usuario = { id: 2, name: 'Coordinadora', email: 'coordinadora@uta.cl', rol: 'coordinadora' };

  const iniciar = (token = 'sesion-original') => {
    auth.login(usuario.email, 'clave-de-prueba').subscribe();
    http.expectOne('/api/login').flush({ token, usuario });
  };

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    });
    auth = TestBed.inject(Auth);
    http = TestBed.inject(HttpTestingController);
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
    vi.useRealTimers();
  });

  it('guarda el token y el usuario al iniciar sesión', () => {
    iniciar();
    expect(auth.token).toBe('sesion-original');
    expect(auth.usuario()).toEqual(usuario);
    expect(auth.inicio()).toBe('/coordinacion');
  });

  it('limpia y redirige inmediatamente, enviando el token original para revocarlo', () => {
    iniciar();
    localStorage.setItem('preferencia', 'conservar');
    auth.logout();

    expect(auth.token).toBeNull();
    expect(localStorage.getItem('usuario')).toBeNull();
    expect(auth.usuario()).toBeNull();
    expect(auth.autenticado()).toBe(false);
    expect(localStorage.getItem('preferencia')).toBe('conservar');
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalledWith('/auth', { replaceUrl: true });
    expect(auth.estadoCierre()).toBe('pendiente');

    const peticion = http.expectOne('/api/logout');
    expect(peticion.request.method).toBe('POST');
    expect(peticion.request.headers.get('Authorization')).toBe('Bearer sesion-original');
    peticion.flush(null, { status: 204, statusText: 'No Content' });
    expect(auth.estadoCierre()).toBe('revocado');
  });

  it('no duplica la revocación ante clics repetidos', () => {
    iniciar();
    auth.logout();
    auth.logout();
    auth.logout();
    http.expectOne('/api/logout').flush(null, { status: 204, statusText: 'No Content' });
    expect(auth.estadoCierre()).toBe('revocado');
  });

  it('sin token limpia el estado y redirige sin afirmar una revocación', () => {
    localStorage.setItem('usuario', JSON.stringify(usuario));
    auth.logout();
    http.expectNone('/api/logout');
    expect(localStorage.getItem('usuario')).toBeNull();
    expect(auth.estadoCierre()).toBe('ninguno');
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalledWith('/auth', { replaceUrl: true });
  });

  it('trata el 401 como acceso ya inválido y no redirige dos veces', () => {
    iniciar();
    auth.logout();
    http.expectOne('/api/logout').flush({}, { status: 401, statusText: 'Unauthorized' });
    expect(auth.estadoCierre()).toBe('invalido');
    expect(auth.autenticado()).toBe(false);
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalledTimes(1);
  });

  it.each([500, 503])('distingue el cierre local de la revocación ante un error %i', (status) => {
    iniciar();
    auth.logout();
    http.expectOne('/api/logout').flush({}, { status, statusText: 'Server Error' });
    expect(auth.estadoCierre()).toBe('solo_local');
    expect(auth.token).toBeNull();
    expect(auth.usuario()).toBeNull();
  });

  it('mantiene el cierre local cuando falla la conexión', () => {
    iniciar();
    auth.logout();
    http.expectOne('/api/logout').error(new ProgressEvent('error'));
    expect(auth.estadoCierre()).toBe('solo_local');
    expect(auth.autenticado()).toBe(false);
    expect(localStorage.getItem('token')).toBeNull();
  });

  it('deja de esperar después de diez segundos sin confirmar una revocación', () => {
    vi.useFakeTimers();
    iniciar();
    auth.logout();
    const peticion = http.expectOne('/api/logout');
    vi.advanceTimersByTime(10_000);
    expect(peticion.cancelled).toBe(true);
    expect(auth.estadoCierre()).toBe('solo_local');
  });

  it.each([204, 401, 503])('permite volver a ingresar y descarta el resultado %i del logout anterior', (status) => {
    iniciar();
    auth.logout();
    const cierreAnterior = http.expectOne('/api/logout');
    iniciar('sesion-nueva');
    cierreAnterior.flush(null, { status, statusText: 'Respuesta anterior' });
    expect(auth.token).toBe('sesion-nueva');
    expect(auth.autenticado()).toBe(true);
    expect(auth.estadoCierre()).toBe('ninguno');
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalledTimes(1);
  });

  it('cancela peticiones autenticadas pendientes al cerrar sesión', () => {
    iniciar();
    const recibir = vi.fn();
    TestBed.inject(HttpClient).get('/api/me').subscribe(recibir);
    const datosAnteriores = http.expectOne('/api/me');
    auth.logout();
    expect(datosAnteriores.cancelled).toBe(true);
    expect(recibir).not.toHaveBeenCalled();
    http.expectOne('/api/logout').flush(null);
  });

  it('un login pendiente no restaura la sesión después de cerrarla', () => {
    auth.login(usuario.email, 'clave-de-prueba').subscribe();
    const loginAnterior = http.expectOne('/api/login');
    auth.logout();
    expect(loginAnterior.cancelled).toBe(true);
    expect(auth.autenticado()).toBe(false);
    http.expectNone('/api/logout');
  });

  it('limpia la vista si la misma sesión se cierra en otra pestaña, sin duplicar logout', () => {
    iniciar();
    localStorage.removeItem('token');
    window.dispatchEvent(new StorageEvent('storage', {
      key: 'token', oldValue: 'sesion-original', newValue: null, storageArea: localStorage,
    }));
    expect(auth.usuario()).toBeNull();
    expect(localStorage.getItem('usuario')).toBeNull();
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalledWith('/auth', { replaceUrl: true });
    http.expectNone('/api/logout');
  });
});
