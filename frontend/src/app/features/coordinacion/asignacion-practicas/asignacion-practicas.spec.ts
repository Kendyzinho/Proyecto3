import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { of } from 'rxjs';

import { AsignacionPracticas } from './asignacion-practicas';
import { FechasHorario } from './fechas-horario';

const HABILITACION = { id: 5, estudiante_id: 2, estudiante: 'Camila Rojas', email: 'c@uta.cl', categoria: 'laboral', periodo_id: 2, tipo_practica: 'Laboral I', anio: 2026, semestre: 2, areas: [] as string[] };
const AREAS = [{ id: 1, nombre: 'Fotografía' }, { id: 2, nombre: 'Producción audiovisual' }];
const CENTRO = { id: 8, nombre: 'Municipalidad', razon_social: 'Muni', rut: null, direccion: null, es_interno: false, cupos: 2, ocupados: 0, periodo_id: 2, categoria: 'laboral', areas_coincidentes: ['Fotografía'], supervisores: [{ id: 3, nombre: 'Paula', correo: 'p@x.cl' }] };
const SIN_DEFINIR = { id: 20, estudiante: 'Andrea Soto', tipo_practica: 'Laboral I', centro: 'Fundación', fecha_inicio: null, fecha_fin: null, horario: null };
const DEFINIDA = { id: 21, estudiante: 'Matías Vera', tipo_practica: 'Laboral II', centro: 'Museo', fecha_inicio: '2026-11-16', fecha_fin: '2026-12-18', horario: { dias: [1, 3, 5], hora_desde: '09:00', hora_hasta: '13:00' } };

describe('AsignacionPracticas', () => {
  let fixture: ComponentFixture<AsignacionPracticas>;
  let http: HttpTestingController;
  let dialogo: { open: ReturnType<typeof vi.fn> };
  const componente = () => fixture.componentInstance as unknown as {
    idsAreas: { (): number[]; set(v: number[]): void }; guardarAreas(): void; aviso(): string; asignar(): void; seleccionarCentro(id: number): void; centros(): unknown[];
  };

  function iniciar(areas: string[] = []) {
    http.expectOne('/api/areas').flush({ data: AREAS });
    http.expectOne('/api/coordinacion/calendarios').flush({ data: [SIN_DEFINIR, DEFINIDA] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones').flush({ data: [{ ...HABILITACION, areas }] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones/5/centros').flush({ data: [] });
    fixture.detectChanges();
  }

  beforeEach(() => {
    dialogo = { open: vi.fn(() => ({ afterClosed: () => of(true) })) };
    TestBed.configureTestingModule({
      imports: [AsignacionPracticas],
      providers: [provideHttpClient(), provideHttpClientTesting(), { provide: MatDialog, useValue: dialogo }],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(AsignacionPracticas);
    fixture.detectChanges();
  });
  afterEach(() => http.verify());

  it('deja definir las áreas de un estudiante sin áreas y vuelve a buscar sus centros', () => {
    iniciar();
    expect(fixture.nativeElement.textContent).toContain('Sin áreas registradas');

    componente().idsAreas.set([1]);
    componente().guardarAreas();
    const req = http.expectOne('/api/coordinacion/asignaciones/habilitaciones/5/areas');
    expect(req.request.method).toBe('PUT');
    expect(req.request.body).toEqual({ area_ids: [1] });
    req.flush({ data: { areas: ['Fotografía'] } });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones/5/centros').flush({ data: [CENTRO] });
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).not.toContain('Sin áreas registradas');
    expect(fixture.nativeElement.textContent).toContain('Municipalidad');
  });

  it('precarga las áreas actuales del estudiante', () => {
    iniciar(['Producción audiovisual']);
    expect(componente().idsAreas()).toEqual([2]);
  });

  it('no guarda si las áreas no cambiaron', () => {
    iniciar(['Fotografía']);
    componente().guardarAreas();
    http.expectNone('/api/coordinacion/asignaciones/habilitaciones/5/areas');
  });

  it('no deja quitar todas las áreas', () => {
    iniciar(['Fotografía']);
    componente().idsAreas.set([]);
    componente().guardarAreas();
    http.expectNone('/api/coordinacion/asignaciones/habilitaciones/5/areas');
    expect(componente().aviso()).toContain('al menos un área');
    expect(componente().idsAreas()).toEqual([1]);
  });

  it('muestra fechas y horario de las prácticas y marca lo que falta', () => {
    iniciar();
    const filas = [...fixture.nativeElement.querySelectorAll('.calendarios tbody tr')] as HTMLElement[];
    expect(filas[0].textContent).toContain('Andrea Soto');
    expect(filas[0].textContent).toContain('Sin definir');
    expect(filas[0].querySelector('button')?.textContent).toContain('Definir');
    expect(filas[1].textContent).toContain('16-11-2026 a 18-12-2026');
    expect(filas[1].textContent).toContain('Lun, Mié, Vie, 09:00 a 13:00');
    expect(filas[1].querySelector('button')?.textContent).toContain('Editar');
  });

  it('abre el diálogo con la práctica y actualiza la lista al guardar', () => {
    iniciar();
    (fixture.nativeElement.querySelector('.calendarios tbody tr button') as HTMLButtonElement).click();
    expect(dialogo.open).toHaveBeenCalledWith(FechasHorario, expect.objectContaining({ data: SIN_DEFINIR }));
    http.expectOne('/api/coordinacion/calendarios').flush({ data: [DEFINIDA] });
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelectorAll('.calendarios tbody tr').length).toBe(1);
  });

  it('mantiene visible el aviso de asignación aunque queden estudiantes pendientes', () => {
    http.expectOne('/api/areas').flush({ data: AREAS });
    http.expectOne('/api/coordinacion/calendarios').flush({ data: [] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones').flush({ data: [{ ...HABILITACION, areas: ['Fotografía'] }] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones/5/centros').flush({ data: [CENTRO] });

    componente().seleccionarCentro(8);
    componente().asignar();
    http.expectOne('/api/coordinacion/asignaciones').flush({ message: 'Práctica asignada.', data: { id: 30 } });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones').flush({ data: [{ ...HABILITACION, id: 6, areas: ['Fotografía'] }] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones/6/centros').flush({ data: [] });
    http.expectOne('/api/coordinacion/calendarios').flush({ data: [] });

    expect(componente().aviso()).toContain('Práctica asignada.');
  });

  it('al asignar, la práctica nueva aparece en la lista de fechas y horario', () => {
    http.expectOne('/api/areas').flush({ data: AREAS });
    http.expectOne('/api/coordinacion/calendarios').flush({ data: [] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones').flush({ data: [{ ...HABILITACION, areas: ['Fotografía'] }] });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones/5/centros').flush({ data: [CENTRO] });

    componente().seleccionarCentro(8);
    componente().asignar();
    http.expectOne('/api/coordinacion/asignaciones').flush({ message: 'Práctica asignada.', data: { id: 30 } });
    http.expectOne('/api/coordinacion/asignaciones/habilitaciones').flush({ data: [] });
    http.expectOne('/api/coordinacion/calendarios').flush({ data: [SIN_DEFINIR] });
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('.calendarios tbody tr')?.textContent).toContain('Andrea Soto');
  });
});
