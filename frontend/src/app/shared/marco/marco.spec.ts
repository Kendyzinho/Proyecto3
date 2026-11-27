import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { MatMenuHarness } from '@angular/material/menu/testing';
import { Router, provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { authInterceptor } from '../../core/interceptors/auth-interceptor';
import { Auth } from '../../core/services/auth';

import { Marco, iniciales } from './marco';

describe('Marco', () => {
  beforeEach(() => {
    localStorage.setItem('token', 'sesion-marco');
    localStorage.setItem('usuario', JSON.stringify({ id: 1, name: 'Marta Rojas', email: 'secretaria@uta.cl', rol: 'secretaria' }));
  });

  afterEach(() => localStorage.clear());

  it('muestra el usuario, su rol y las opciones del menú', async () => {
    await TestBed.configureTestingModule({
      imports: [Marco],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    const fixture = TestBed.createComponent(Marco);
    fixture.componentRef.setInput('grupo', 'Secretaría de Jefatura');
    fixture.componentRef.setInput('menu', [{ texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' }]);
    fixture.detectChanges();
    await fixture.whenStable();

    const texto = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(texto).toContain('Marta Rojas');
    expect(texto).toContain('Secretaria de Jefatura');
    expect(texto).toContain('Nómina de estudiantes');
  });

  it('arma las iniciales con las dos primeras palabras', () => {
    expect(iniciales('Ignacia Muñoz Vega')).toBe('IM');
    expect(iniciales('')).toBe('');
  });

  it('reutiliza un único menú y cierra la sesión desde el layout de Secretaría', async () => {
    localStorage.setItem('token', 'sesion-marco');
    await TestBed.configureTestingModule({
      imports: [Marco],
      providers: [provideHttpClient(withInterceptors([authInterceptor])), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();
    const router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    const fixture = TestBed.createComponent(Marco);
    fixture.componentRef.setInput('grupo', 'Secretaría de Jefatura');
    fixture.componentRef.setInput('menu', []);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelectorAll('app-menu-usuario')).toHaveLength(1);
    const menus = await TestbedHarnessEnvironment.loader(fixture).getAllHarnesses(MatMenuHarness);
    expect(menus).toHaveLength(1);
    await menus[0].open();
    const [cerrar] = await menus[0].getItems({ text: /Cerrar sesión/ });
    await cerrar.click();
    expect(TestBed.inject(Auth).usuario()).toBeNull();
    expect(localStorage.getItem('token')).toBeNull();
    expect(router.navigateByUrl).toHaveBeenCalledWith('/auth', { replaceUrl: true });
    const http = TestBed.inject(HttpTestingController);
    const solicitud = http.expectOne('/api/logout');
    expect(solicitud.request.headers.get('Authorization')).toBe('Bearer sesion-marco');
    solicitud.flush(null, { status: 204, statusText: 'No Content' });
    http.verify();
  });

  // HU-14: la campana muestra a cada rol lo que le toca y lleva a donde se resuelve
  describe('campana', () => {
    const lineas = [
      { id: 7, tipo: 'abandono', fecha_referencia: '2026-11-11', practica_id: 4, estudiante: 'Diego Choque Vargas', otras: 2, enlace: null },
      { id: 9, tipo: 'sin_confirmar', fecha_referencia: '2026-11-02', practica_id: 5, estudiante: 'Antonia Vega Muñoz', otras: 0, enlace: 'https://practicas.test/supervisor/asistencia/5/2026-11-02?expires=1&signature=abc' },
    ];

    async function abrirComo(rol: string, data: unknown[]) {
      localStorage.setItem('usuario', JSON.stringify({ id: 2, name: 'Usuario', email: 'usuario@uta.cl', rol }));
      await TestBed.configureTestingModule({
        imports: [Marco],
        providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
      }).compileComponents();
      const fixture = TestBed.createComponent(Marco);
      fixture.componentRef.setInput('menu', []);
      fixture.detectChanges();
      const http = TestBed.inject(HttpTestingController);
      http.match((r) => r.url !== '/api/notificaciones').forEach((r) => r.flush({ data: [] }));
      http.expectOne('/api/notificaciones').flush({ data, meta: { total: data.length } });
      fixture.detectChanges();
      const el = fixture.nativeElement as HTMLElement;
      el.querySelector<HTMLButtonElement>('.notificaciones > button')!.click();
      fixture.detectChanges();
      http.match('/api/notificaciones').forEach((r) => r.flush({ data, meta: { total: data.length } }));
      fixture.detectChanges();
      return { fixture, el, http };
    }

    it('a la Coordinadora le muestra una línea por estudiante y abre su ficha', async () => {
      const { el } = await abrirComo('coordinadora', [lineas[0]]);
      const abrir = vi.spyOn(TestBed.inject(MatDialog), 'open').mockReturnValue({ afterClosed: () => ({ subscribe: () => {} }) } as never);

      expect(el.querySelector('.notificaciones__contador')?.textContent).toContain('1');
      const item = el.querySelector<HTMLButtonElement>('.notificaciones__lista button')!;
      expect(item.textContent).toContain('Diego Choque Vargas');
      expect(item.textContent).toContain('3 días seguidos sin marcar desde el 11/11, y 2 alertas más');
      expect(item.textContent).not.toContain('·');
      item.click();
      expect(abrir).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ data: 4 }));
    });

    it('al estudiante le habla de su bitácora y lo lleva a ella', async () => {
      const { el } = await abrirComo('estudiante', [lineas[0]]);
      const navegar = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);

      const item = el.querySelector<HTMLButtonElement>('.notificaciones__lista button')!;
      expect(item.textContent).toContain('Llevas 3 días sin marcar tu bitácora');
      item.click();
      expect(navegar).toHaveBeenCalledWith('/estudiante/bitacora');
    });

    it('al supervisor lo lleva a confirmar la semana dentro del sistema', async () => {
      const { el } = await abrirComo('supervisor', [lineas[1]]);
      const navegar = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);

      const item = el.querySelector<HTMLButtonElement>('.notificaciones__lista button')!;
      expect(item.textContent).toContain('Semana del 02/11 sin confirmar');
      item.click();
      expect(navegar).toHaveBeenCalledWith('/supervisor/asistencia/5/2026-11-02?expires=1&signature=abc');
    });

    it('sin pendientes no muestra contador', async () => {
      const { el } = await abrirComo('estudiante', []);

      expect(el.querySelector('.notificaciones__contador')).toBeNull();
      expect(el.querySelector('.notificaciones__panel')?.textContent).toContain('Tu bitácora está al día.');
    });
  });
});
