import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { PercepcionCentro } from './percepcion-centro';

describe('Percepción del centro', () => {
  it('carga solo las prácticas de la API y muestra la encuesta', async () => {
    await TestBed.configureTestingModule({ imports: [PercepcionCentro], providers: [provideHttpClient(), provideHttpClientTesting()] }).compileComponents();
    const fixture = TestBed.createComponent(PercepcionCentro);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/estudiante/percepciones-centro').flush({ data: [
      { id: 7, tipo: 'Laboral I', centro: 'Centro UTA', estado: 'en_curso', percepcion: null },
    ] });
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Percepción del centro de práctica');
    expect(fixture.nativeElement.textContent).toContain('Práctica');
    http.verify();
  });

  it('exige las tres valoraciones y guarda la respuesta de la práctica elegida', async () => {
    await TestBed.configureTestingModule({ imports: [PercepcionCentro], providers: [provideHttpClient(), provideHttpClientTesting()] }).compileComponents();
    const fixture = TestBed.createComponent(PercepcionCentro);
    fixture.detectChanges();
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/estudiante/percepciones-centro').flush({ data: [
      { id: 7, tipo: 'Laboral I', centro: 'Centro UTA', estado: 'en_curso', percepcion: null },
    ] });
    (fixture.componentInstance as any).seleccionar(7);
    fixture.detectChanges();
    await fixture.whenStable();
    const boton = fixture.nativeElement.querySelector('button[type="submit"]') as HTMLButtonElement;
    expect(boton.disabled).toBe(true);
    (fixture.componentInstance as any).respuesta = { clima_laboral: 4, pago_viatico: 3, pertinencia_tareas: 5, observaciones: 'Buen apoyo.' };
    fixture.detectChanges();
    await fixture.whenStable();
    expect(boton.disabled).toBe(false);
    boton.click();
    const solicitud = http.expectOne('/api/estudiante/practicas/7/percepcion-centro');
    expect(solicitud.request.method).toBe('PUT');
    expect(solicitud.request.body).toEqual({ clima_laboral: 4, pago_viatico: 3, pertinencia_tareas: 5, observaciones: 'Buen apoyo.' });
    solicitud.flush({ data: solicitud.request.body });
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Percepción guardada correctamente.');
    http.verify();
  });
});
