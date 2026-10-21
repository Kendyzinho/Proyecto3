import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Subject } from 'rxjs';
import { InicioCoordinacion } from './inicio';
import { NuevaPractica } from './nueva-practica';

describe('InicioCoordinacion', () => {
  let fixture: ComponentFixture<InicioCoordinacion>;
  let http: HttpTestingController;
  let cerrado: Subject<boolean>;
  let dialogo: { open: ReturnType<typeof vi.fn> };
  let aviso: { open: ReturnType<typeof vi.fn> };
  beforeEach(() => {
    cerrado = new Subject<boolean>();
    dialogo = { open: vi.fn(() => ({ afterClosed: () => cerrado })) };
    aviso = { open: vi.fn() };
    TestBed.configureTestingModule({ imports: [InicioCoordinacion], providers: [provideHttpClient(), provideHttpClientTesting(),
      { provide: MatDialog, useValue: dialogo }, { provide: MatSnackBar, useValue: aviso }] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(InicioCoordinacion); fixture.detectChanges();
  });
  afterEach(() => http.verify());
  function metricas(total: number) { http.expectOne('/api/coordinacion/metricas').flush({ periodo: { id: 2, nombre: '2º semestre 2026' }, practicas_registradas: total }); fixture.detectChanges(); }

  it('abre el formulario y vuelve a consultar el total después de registrar', () => {
    metricas(5);
    expect(fixture.nativeElement.querySelector('header button').textContent).toContain('Registro excepcional');
    fixture.nativeElement.querySelector('header button').click();
    expect(dialogo.open).toHaveBeenCalledWith(NuevaPractica, expect.objectContaining({ data: { id: 2, nombre: '2º semestre 2026' } }));
    cerrado.next(true); metricas(6);
    expect(fixture.nativeElement.querySelector('strong').textContent).toBe('6');
    expect(aviso.open).toHaveBeenCalled();
  });
  it('cancelar conserva el total y no muestra éxito', () => {
    metricas(5); fixture.nativeElement.querySelector('header button').click(); cerrado.next(false);
    http.expectNone('/api/coordinacion/metricas'); expect(aviso.open).not.toHaveBeenCalled();
  });
  it('muestra carga y permite recuperar una métrica fallida', () => {
    expect(fixture.nativeElement.textContent).toContain('Cargando métricas');
    http.expectOne('/api/coordinacion/metricas').flush({}, { status: 500, statusText: 'Error' }); fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('No se pudo actualizar');
    fixture.nativeElement.querySelector('section button').click(); metricas(5);
  });
  it('no permite registro sin período', () => {
    http.expectOne('/api/coordinacion/metricas').flush({ periodo: null, practicas_registradas: 0 }); fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('header button').disabled).toBe(true);
    expect(fixture.nativeElement.textContent).toContain('No hay un período académico');
  });
});
