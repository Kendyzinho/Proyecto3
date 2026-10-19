import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestbedHarnessEnvironment } from '@angular/cdk/testing/testbed';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';
import { MatSelectHarness } from '@angular/material/select/testing';
import { FormGroup } from '@angular/forms';
import { NuevaPractica } from './nueva-practica';
import { CatalogoExcepcional } from './practicas-api';

const CATALOGO: CatalogoExcepcional = {
  periodo: { id: 2, nombre: '2º semestre 2026' },
  habilitaciones: [{ id: 7, estudiante: 'Estudiante de prueba', tipo_practica: 'Laboral I' }],
  supervisores: [{ id: 3, nombre: 'Carolina', centro: 'Fundación UTA' }],
};

describe('NuevaPractica', () => {
  let fixture: ComponentFixture<NuevaPractica>;
  let http: HttpTestingController;
  let referencia: { close: ReturnType<typeof vi.fn>; disableClose: boolean };
  const form = () => (fixture.componentInstance as unknown as { form: FormGroup }).form;
  const guardar = () => fixture.nativeElement.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  const completar = () => form().setValue({ habilitacion_id: 7, supervisor_id: 3, modalidad: 'mixta', fecha_inicio: '2026-11-01', fecha_fin: '2026-12-01', justificacion_excepcional: 'Regularización autorizada.' });

  beforeEach(() => {
    referencia = { close: vi.fn(), disableClose: false };
    TestBed.configureTestingModule({ imports: [NuevaPractica], providers: [provideHttpClient(), provideHttpClientTesting(),
      { provide: MAT_DIALOG_DATA, useValue: CATALOGO.periodo }, { provide: MatDialogRef, useValue: referencia }] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(NuevaPractica);
    fixture.detectChanges();
  });
  afterEach(() => http.verify());
  function cargar(datos = CATALOGO) { http.expectOne('/api/coordinacion/practicas-excepcionales/catalogo?periodo_id=2').flush(datos); fixture.detectChanges(); }

  it('muestra carga, obtiene opciones reales y deriva un centro de solo lectura', () => {
    expect(fixture.nativeElement.querySelector('.cabecera > [mat-dialog-title]')?.textContent).toBe('Registrar práctica excepcional');
    expect(fixture.nativeElement.querySelector('.tipo-registro')).toBeNull();
    expect(fixture.nativeElement.querySelector('.descripcion')).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('Cargando estudiantes');
    cargar(); completar(); fixture.detectChanges();
    const centro = fixture.nativeElement.querySelector('input[readonly]');
    expect(centro.value).toBe('Fundación UTA');
    expect(fixture.nativeElement.textContent).toContain('Disponibilidad de cupos no determinada');
  });
  it('ofrece solamente Presencial y Mixta, con Presencial como valor inicial', async () => {
    cargar();
    expect(form().controls['modalidad'].value).toBe('presencial');
    const select = await TestbedHarnessEnvironment.loader(fixture).getHarness(
      MatSelectHarness.with({ selector: '[formcontrolname="modalidad"]' }),
    );
    await select.open();
    const opciones = await select.getOptions();
    expect(await Promise.all(opciones.map((opcion) => opcion.getText()))).toEqual(['Presencial', 'Mixta']);
  });
  it('cancela sin enviar registros', () => {
    cargar(); fixture.nativeElement.querySelector('[aria-label="Cerrar formulario"]').click();
    expect(referencia.close).toHaveBeenCalledWith(false);
    http.expectNone('/api/coordinacion/practicas-excepcionales');
  });
  it('valida campos, espacios y fechas invertidas', () => {
    cargar(); guardar(); expect(form().invalid).toBe(true);
    completar(); form().patchValue({ justificacion_excepcional: '   ' }); guardar(); expect(form().invalid).toBe(true);
    completar(); form().patchValue({ fecha_fin: '2026-10-01' }); guardar(); expect(form().hasError('fechas')).toBe(true);
    completar(); form().patchValue({ modalidad: 'a_distancia' }); guardar(); expect(form().invalid).toBe(true);
    http.expectNone('/api/coordinacion/practicas-excepcionales');
  });
  it.each(['presencial', 'mixta'])('envía una sola solicitud para modalidad %s', (modalidad) => {
    cargar(); completar(); form().patchValue({ modalidad }); guardar(); guardar();
    expect(referencia.disableClose).toBe(true);
    const req = http.expectOne('/api/coordinacion/practicas-excepcionales');
    expect(req.request.body.periodo_id).toBe(2);
    expect(req.request.body.modalidad).toBe(modalidad);
    expect(req.request.body.centro_id).toBeUndefined();
    expect(req.request.body.origen).toBeUndefined();
    req.flush({ data: { id: 10 } }); expect(referencia.close).toHaveBeenCalledWith(true);
  });
  it('presenta la validación del backend y permite corregir', () => {
    cargar(); completar(); guardar();
    http.expectOne('/api/coordinacion/practicas-excepcionales').flush({ errors: { habilitacion_id: ['Esta habilitación ya tiene una práctica registrada.'] } }, { status: 422, statusText: 'Unprocessable' });
    fixture.detectChanges();
    expect(form().get('habilitacion_id')?.getError('servidor')).toContain('ya tiene');
    expect(referencia.disableClose).toBe(false);
    expect(referencia.close).not.toHaveBeenCalled();
  });
  it('muestra ausencia de candidatos y desactiva el registro', () => {
    cargar({ ...CATALOGO, habilitaciones: [] });
    expect(fixture.nativeElement.textContent).toContain('No hay estudiantes habilitados');
    expect(fixture.nativeElement.querySelector('[type="submit"]').disabled).toBe(true);
  });
  it('permite reintentar la carga fallida', () => {
    http.expectOne('/api/coordinacion/practicas-excepcionales/catalogo?periodo_id=2').flush({}, { status: 500, statusText: 'Error' });
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('No se pudieron cargar');
    const boton = [...fixture.nativeElement.querySelectorAll('button')].find((b: any) => b.textContent.includes('Reintentar')) as HTMLButtonElement;
    boton.click(); cargar(); expect(fixture.nativeElement.textContent).not.toContain('No se pudieron cargar');
  });
  it('muestra error de red sin afirmar que se guardó', () => {
    cargar(); completar(); guardar();
    http.expectOne('/api/coordinacion/practicas-excepcionales').error(new ProgressEvent('error'));
    fixture.detectChanges(); expect(fixture.nativeElement.textContent).toContain('No se pudo confirmar el registro');
    expect(referencia.close).not.toHaveBeenCalled();
  });
});
