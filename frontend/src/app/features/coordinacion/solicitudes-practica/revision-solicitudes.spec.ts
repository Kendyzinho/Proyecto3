import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { of } from 'rxjs';

import { NuevaPractica } from '../nueva-practica';
import { RevisionSolicitudes } from './revision-solicitudes';

describe('RevisionSolicitudes', () => {
  let fixture: ComponentFixture<RevisionSolicitudes>;
  let http: HttpTestingController;
  let dialogo: { open: ReturnType<typeof vi.fn> };
  const solicitud = (id: number, estado: string) => ({
    id, estudianteNombre: `Estudiante ${id}`, estudianteCorreo: 'e@alumnos.uta.cl', estado, habilitacionCumplida: true, modoCentro: 'Registrado',
    centroId: 1, centroRazonSocial: 'Centro SpA', centroRut: '76.543.210-3', modoSupervisor: 'Registrado', supervisorId: 's@c.cl', supervisorNombre: 'Sara',
    supervisorCorreo: 's@c.cl', supervisorCargo: '', supervisorTelefono: '', supervisorUnidad: '', tareas: 'Diseño',
    horario: { modalidad: 'Presencial', fechaInicio: '2026-11-10', fechaTermino: '2026-12-10', dias: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'], horaDesde: '09:00', horaHasta: '13:00' },
    motivoDevolucion: null, actualizadaEn: '2026-11-01T10:00:00Z',
  });

  beforeEach(() => {
    dialogo = { open: vi.fn(() => ({ afterClosed: () => of(false) })) };
    TestBed.configureTestingModule({
      imports: [RevisionSolicitudes],
      providers: [provideHttpClient(), provideHttpClientTesting(), { provide: MatDialog, useValue: dialogo }],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(RevisionSolicitudes);
    fixture.detectChanges();
    http.expectOne('/api/solicitudes-practica').flush({ data: [solicitud(1, 'Por revisar'), solicitud(2, 'Devuelta')] });
    http.expectOne('/api/centros-practica').flush({ data: [] });
    fixture.detectChanges();
  });

  afterEach(() => http.verify());

  it('"Nueva práctica" abre el registro excepcional en vez de una pantalla aparte', () => {
    fixture.nativeElement.querySelector('.encabezado-pagina button').click();
    http.expectOne('/api/coordinacion/metricas').flush({ periodo: { id: 2, nombre: '2º semestre 2026' }, practicas_registradas: 0 });
    expect(dialogo.open).toHaveBeenCalledWith(NuevaPractica, expect.objectContaining({ data: { id: 2, nombre: '2º semestre 2026' } }));
  });

  it('muestra el horario de lunes a viernes y exige motivo para devolver', () => {
    expect(fixture.nativeElement.textContent).toContain('Lunes a viernes, 09:00 a 13:00');
    const devolver = [...fixture.nativeElement.querySelectorAll('button')].find((b: any) => b.textContent.includes('Devolver')) as HTMLButtonElement;
    devolver.click();
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Escribe el motivo');
    http.expectNone('/api/practicas/1/decision');
  });
});
