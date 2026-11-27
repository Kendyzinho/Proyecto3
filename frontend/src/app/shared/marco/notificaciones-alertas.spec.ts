import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Auth } from '../../core/services/auth';
import { NotificacionesAlertas } from './notificaciones-alertas';

// HU-14: la campana no le muestra a quien entra lo que veía la sesión anterior
describe('NotificacionesAlertas', () => {
  const linea = { id: 7, tipo: 'abandono', fecha_referencia: '2026-11-11', practica_id: 4, estudiante: 'Diego Choque Vargas', otras: 0, enlace: null };

  beforeEach(() => {
    localStorage.setItem('token', 'sesion-coordinadora');
    localStorage.setItem('usuario', JSON.stringify({ id: 2, name: 'Coordinadora', email: 'coordinadora@uta.cl', rol: 'coordinadora' }));
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([{ path: 'auth', children: [] }])] });
  });

  afterEach(() => localStorage.clear());

  it('se vacía al cerrar sesión', () => {
    const notificaciones = TestBed.inject(NotificacionesAlertas);
    const http = TestBed.inject(HttpTestingController);
    notificaciones.actualizar();
    http.expectOne('/api/notificaciones').flush({ data: [linea], meta: { total: 1 } });
    expect(notificaciones.total()).toBe(1);

    TestBed.inject(Auth).logout();

    expect(notificaciones.total()).toBe(0);
    expect(notificaciones.lineas()).toEqual([]);
  });

  it('descarta la respuesta que llega después de cerrar sesión', () => {
    const notificaciones = TestBed.inject(NotificacionesAlertas);
    const http = TestBed.inject(HttpTestingController);
    notificaciones.actualizar();

    TestBed.inject(Auth).logout();
    http.expectOne('/api/notificaciones').flush({ data: [linea], meta: { total: 1 } });

    expect(notificaciones.total()).toBe(0);
  });
});
