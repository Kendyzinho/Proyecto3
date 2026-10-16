import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Auth } from './auth';

describe('Auth', () => {
  let auth: Auth;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([{ path: 'auth', children: [] }])],
    });
    auth = TestBed.inject(Auth);
    http = TestBed.inject(HttpTestingController);
  });

  it('guarda el token y el usuario al iniciar sesión', () => {
    auth.login('coordinadora@uta.cl', 'password').subscribe();
    http.expectOne('/api/login').flush({
      token: 'abc',
      usuario: { id: 2, name: 'Coordinadora', email: 'coordinadora@uta.cl', rol: 'coordinadora' },
    });

    expect(auth.token).toBe('abc');
    expect(auth.autenticado()).toBe(true);
    expect(auth.inicio()).toBe('/coordinacion');
  });

  it('borra la sesión al cerrarla', () => {
    localStorage.setItem('token', 'abc');
    auth.logout();
    http.expectOne('/api/logout').flush({});

    expect(auth.token).toBeNull();
    expect(auth.autenticado()).toBe(false);
  });
});
