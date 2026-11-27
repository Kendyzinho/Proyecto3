import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { vi } from 'vitest';

import { AgregarEstudiante, DatosAgregarEstudiante } from './agregar-estudiante';

// HU-07: el diálogo busca primero entre los estudiantes registrados
describe('AgregarEstudiante', () => {
  const datos: DatosAgregarEstudiante = {
    periodo: { id: 3, nombre: '2º semestre 2026' } as DatosAgregarEstudiante['periodo'],
    practicas: [{ id: 5, nombre: 'Laboral I' } as DatosAgregarEstudiante['practicas'][number]],
    areas: [{ id: 1, nombre: 'Fotografía' }],
  };
  const antonia = { rut: '21.502.611-6', nombre: 'Antonia Vega Muñoz', correo: 'antonia.vega@alumnos.uta.cl', anio_ingreso: 2024, area_ids: [1], activo: true };
  const cerrar = vi.fn();

  async function crear() {
    await TestBed.configureTestingModule({
      imports: [AgregarEstudiante],
      providers: [
        provideHttpClient(), provideHttpClientTesting(),
        { provide: MAT_DIALOG_DATA, useValue: datos },
        { provide: MatDialogRef, useValue: { close: cerrar } },
      ],
    }).compileComponents();
    const fixture = TestBed.createComponent(AgregarEstudiante);
    fixture.detectChanges();
    return { fixture, el: fixture.nativeElement as HTMLElement, http: TestBed.inject(HttpTestingController) };
  }

  async function buscar(fixture: Awaited<ReturnType<typeof crear>>['fixture'], texto: string) {
    const campo = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>('input[type=search]')!;
    campo.value = texto;
    campo.dispatchEvent(new Event('input'));
    await new Promise((listo) => setTimeout(listo, 300));
  }

  afterEach(() => TestBed.inject(HttpTestingController).verify());

  it('parte buscando y no muestra el formulario ni el botón para agregar', async () => {
    const { el } = await crear();

    expect(el.querySelector('input[type=search]')).not.toBeNull();
    expect(el.querySelector('input[formControlName=nombre]')).toBeNull();
    expect(el.textContent).not.toContain('Agregar estudiante');
  });

  it('agrega a un estudiante registrado sin volver a escribir sus datos', async () => {
    const { fixture, el, http } = await crear();
    await buscar(fixture, 'Antonia');
    http.expectOne('/api/nomina/estudiantes?periodo_id=3&buscar=Antonia').flush({ data: [antonia] });
    fixture.detectChanges();

    el.querySelector<HTMLButtonElement>('.resultados button')!.click();
    fixture.detectChanges();
    expect(el.querySelector('.elegido')?.textContent).toContain('Antonia Vega Muñoz');
    expect(el.querySelector('input[formControlName=nombre]')).toBeNull();

    fixture.componentInstance['form'].controls.tipo_practica_id.setValue(5);
    el.querySelector('form')!.dispatchEvent(new Event('submit'));

    const peticion = http.expectOne({ method: 'POST', url: '/api/nomina/estudiantes' });
    expect(peticion.request.body).toEqual({
      periodo_id: 3, nombre: 'Antonia Vega Muñoz', rut: '21.502.611-6', correo: 'antonia.vega@alumnos.uta.cl',
      anio_ingreso: 2024, tipo_practica_id: 5, cumple_prerrequisito: true, area_ids: [1],
    });
    peticion.flush({ id: 9 });
    expect(cerrar).toHaveBeenCalledWith(true);
  });

  it('avisa si no hay resultados y deja llenar uno nuevo', async () => {
    const { fixture, el, http } = await crear();
    await buscar(fixture, '21999');
    http.expectOne('/api/nomina/estudiantes?periodo_id=3&buscar=21999').flush({ data: [] });
    fixture.detectChanges();

    expect(el.textContent).toContain('No hay estudiantes registrados con ese RUT o nombre, o ya están en la nómina de este período.');
    [...el.querySelectorAll<HTMLButtonElement>('button')].find((b) => b.textContent?.includes('Es un estudiante nuevo'))!.click();
    fixture.detectChanges();

    expect(el.querySelector('input[formControlName=nombre]')).not.toBeNull();
    expect(el.textContent).toContain('Agregar estudiante');
  });

  it('sigue buscando después de un error', async () => {
    const { fixture, el, http } = await crear();
    await buscar(fixture, 'Anto');
    http.expectOne('/api/nomina/estudiantes?periodo_id=3&buscar=Anto').flush(null, { status: 500, statusText: 'Error' });
    fixture.detectChanges();
    expect(el.textContent).toContain('No se pudo buscar.');

    await buscar(fixture, 'Antonia');
    http.expectOne('/api/nomina/estudiantes?periodo_id=3&buscar=Antonia').flush({ data: [antonia] });
    fixture.detectChanges();

    expect(el.querySelector('.resultados')?.textContent).toContain('Antonia Vega Muñoz');
    expect(el.textContent).not.toContain('No se pudo buscar.');
  });
});
