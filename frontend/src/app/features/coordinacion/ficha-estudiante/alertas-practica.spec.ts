import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { vi } from 'vitest';

import { NotificacionesAlertas } from '../../../shared/marco/notificaciones-alertas';
import { AlertaFicha, AlertasPractica } from './alertas-practica';

// HU-14: alertas de la práctica dentro de la ficha del estudiante
describe('AlertasPractica', () => {
  const alerta = (id: number, cambios: Partial<AlertaFicha> = {}): AlertaFicha => ({
    id, tipo: 'abandono', fecha_referencia: '2026-11-11', fecha_atencion: null, atendida_por: null, nota: null, fecha_resolucion: null, ...cambios,
  });
  const actualizar = vi.fn();

  async function crear(pendientes: AlertaFicha[], historial: AlertaFicha[]) {
    await TestBed.configureTestingModule({
      imports: [AlertasPractica],
      providers: [
        provideHttpClient(), provideHttpClientTesting(),
        { provide: NotificacionesAlertas, useValue: { actualizar, lineas: signal([]), total: signal(0) } },
      ],
    }).compileComponents();
    const fixture = TestBed.createComponent(AlertasPractica);
    fixture.componentRef.setInput('practicaId', 4);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/coordinacion/practicas/4/alertas').flush({ data: { pendientes, historial } });
    fixture.detectChanges();
    return { fixture, http, el: fixture.nativeElement as HTMLElement };
  }

  afterEach(() => TestBed.inject(HttpTestingController).verify());

  it('no muestra nada si la práctica nunca tuvo alertas', async () => {
    const { el } = await crear([], []);

    expect(el.querySelector('section')).toBeNull();
  });

  it('muestra las pendientes y el historial con quién la atendió o cuándo se resolvió', async () => {
    const { el } = await crear([alerta(1)], [
      alerta(2, { tipo: 'inasistencia', fecha_referencia: '2026-11-16', fecha_atencion: '2026-11-18T10:00:00-03:00', atendida_por: 'Diana Pérez', nota: 'Llamé al supervisor.' }),
      alerta(3, { tipo: 'sin_confirmar', fecha_referencia: '2026-11-02', fecha_resolucion: '2026-11-20T09:00:00-03:00' }),
    ]);

    const texto = el.textContent ?? '';
    expect(texto).toContain('3 días seguidos sin marcar desde el 11/11');
    expect(texto).toContain('Atendida por Diana Pérez el 18/11: Llamé al supervisor.');
    expect(texto).toContain('Se resolvió el 20/11');
    expect(texto).not.toContain('·');
  });

  it('marca como atendida con una nota y avisa a la campana', async () => {
    const { fixture, http, el } = await crear([alerta(1)], []);

    el.querySelector<HTMLButtonElement>('li button')!.click();
    fixture.detectChanges();
    await fixture.whenStable();
    const nota = el.querySelector<HTMLTextAreaElement>('textarea')!;
    nota.value = '  Llamé al supervisor.  ';
    nota.dispatchEvent(new Event('input'));
    fixture.detectChanges();
    await fixture.whenStable();
    el.querySelector('form')!.dispatchEvent(new Event('submit'));

    const peticion = http.expectOne({ method: 'POST', url: '/api/coordinacion/alertas/1/atender' });
    expect(peticion.request.body).toEqual({ nota: 'Llamé al supervisor.' });
    peticion.flush({ data: alerta(1, { fecha_atencion: '2026-11-18T10:00:00-03:00' }) });
    http.expectOne('/api/coordinacion/practicas/4/alertas').flush({ data: { pendientes: [], historial: [alerta(1, { fecha_atencion: '2026-11-18T10:00:00-03:00', atendida_por: 'Diana Pérez', nota: 'Llamé al supervisor.' })] } });
    fixture.detectChanges();

    expect(actualizar).toHaveBeenCalled();
    expect(el.textContent).toContain('No hay alertas abiertas.');
    expect(el.textContent).toContain('Atendida por Diana Pérez');
  });
});
