import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { authInterceptor } from './auth-interceptor';

describe('authInterceptor', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    });
  });

  it('agrega el token a las peticiones', () => {
    localStorage.setItem('token', 'abc');
    TestBed.inject(HttpClient).get('/api/me').subscribe();

    const peticion = TestBed.inject(HttpTestingController).expectOne('/api/me');
    expect(peticion.request.headers.get('Authorization')).toBe('Bearer abc');
    expect(peticion.request.headers.get('Accept')).toBe('application/json');
  });
});
