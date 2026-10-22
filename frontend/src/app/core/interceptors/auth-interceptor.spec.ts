import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { Auth } from '../services/auth';
import { authInterceptor } from './auth-interceptor';

describe('authInterceptor', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem('token', 'abc');
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Usuario', email: 'usuario@uta.cl', rol: 'jefe' }));
    TestBed.configureTestingModule({
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    });
    http = TestBed.inject(HttpTestingController);
    vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
  });

  it('agrega el token y Accept a las peticiones de la API', () => {
    TestBed.inject(HttpClient).get('/api/me').subscribe();
    const peticion = http.expectOne('/api/me');
    expect(peticion.request.headers.get('Authorization')).toBe('Bearer abc');
    expect(peticion.request.headers.get('Accept')).toBe('application/json');
    peticion.flush({});
  });

  it('no envía el token a direcciones externas', () => {
    TestBed.inject(HttpClient).get('https://example.test/recurso').subscribe();
    const peticion = http.expectOne('https://example.test/recurso');
    expect(peticion.request.headers.has('Authorization')).toBe(false);
    peticion.flush({});
  });

  it('ante 401 limpia la sesión y cancela otras peticiones protegidas', () => {
    const cliente = TestBed.inject(HttpClient);
    cliente.get('/api/me').subscribe({ error: () => {} });
    cliente.get('/api/otra').subscribe({ error: () => {} });
    const me = http.expectOne('/api/me');
    const otra = http.expectOne('/api/otra');
    me.flush({}, { status: 401, statusText: 'Unauthorized' });
    expect(TestBed.inject(Auth).usuario()).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
    expect(otra.cancelled).toBe(true);
    expect(TestBed.inject(Router).navigateByUrl).toHaveBeenCalledExactlyOnceWith('/auth', { replaceUrl: true });
  });
});
