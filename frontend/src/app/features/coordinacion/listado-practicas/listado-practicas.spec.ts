import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ListadoPracticas as ListadoPracticasComponent } from './listado-practicas';
import { ListadoPracticas, PracticaListada, filtrarPracticas } from './listado-practicas-api';

const FILAS: PracticaListada[] = [
  {
    id: 1,
    rut: '21.334.120-0',
    estudiante: 'Camila Rojas Pizarro',
    correo: 'camila@alumnos.uta.cl',
    tipo_practica_id: 1,
    practica: 'Laboral I',
    centro: 'Municipalidad de Arica',
    supervisor: 'Paula Contreras',
    estado: 'por_iniciar',
    estado_texto: 'Por iniciar',
    fecha_inicio: '2026-08-17',
    fecha_fin: '2026-09-04',
  },
  {
    id: 2,
    rut: '21.087.552-2',
    estudiante: 'Josefa Ramírez Tapia',
    correo: 'josefa@alumnos.uta.cl',
    tipo_practica_id: 2,
    practica: 'Profesional',
    centro: 'Fundación UTA',
    supervisor: 'Carolina Mamani',
    estado: 'posible_abandono',
    estado_texto: 'Posible abandono',
    fecha_inicio: '2026-09-07',
    fecha_fin: '2026-11-27',
  },
  {
    id: 3,
    rut: '21.502.611-6',
    estudiante: 'Antonia Vega Muñoz',
    correo: 'antonia@alumnos.uta.cl',
    tipo_practica_id: 1,
    practica: 'Laboral II',
    centro: 'Productora Norte Visual',
    supervisor: 'Sergio Paredes',
    estado: 'en_curso',
    estado_texto: 'En curso',
    fecha_inicio: '2026-09-14',
    fecha_fin: '2026-10-02',
  },
];

const LISTADO: ListadoPracticas = {
  periodo: { id: 2, nombre: '2º semestre 2026' },
  catalogo: {
    tipos: [
      { id: 1, nombre: 'Laboral I' },
      { id: 2, nombre: 'Profesional' },
    ],
    estados: [
      { valor: 'en_curso', texto: 'En curso' },
      { valor: 'posible_abandono', texto: 'Posible abandono' },
      { valor: 'por_iniciar', texto: 'Por iniciar' },
    ],
  },
  data: FILAS,
};

describe('ListadoPracticas', () => {
  let fixture: ComponentFixture<ListadoPracticasComponent>;
  let http: HttpTestingController;

  beforeEach(async () => {
    Object.defineProperty(URL, 'createObjectURL', {
      value: vi.fn(() => 'blob:planilla'),
      configurable: true,
    });
    Object.defineProperty(URL, 'revokeObjectURL', { value: vi.fn(), configurable: true });
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    await TestBed.configureTestingModule({
      imports: [ListadoPracticasComponent],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    fixture = TestBed.createComponent(ListadoPracticasComponent);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/coordinacion/practicas').flush(LISTADO);
    fixture.detectChanges();
    await fixture.whenStable();
  });

  afterEach(() => http.verify());

  function texto(): string {
    return (fixture.nativeElement as HTMLElement).textContent ?? '';
  }

  it('muestra la lista general con los tres estados operativos', () => {
    expect(texto()).toContain('Lista general de prácticas');
    expect(texto()).toContain('Camila Rojas Pizarro');
    expect(texto()).toContain('Posible abandono');
    expect(texto()).toContain('En curso');
    expect(texto()).toContain('Por iniciar');
    expect(fixture.nativeElement.querySelectorAll('tr.mat-mdc-row').length).toBe(3);
  });

  it('filtra dinámicamente por búsqueda sin consultar de nuevo', () => {
    const entrada = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>(
      'input[type=search]',
    )!;
    entrada.value = 'munoz';
    entrada.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    expect(texto()).toContain('Antonia Vega Muñoz');
    expect(texto()).not.toContain('Camila Rojas Pizarro');
    http.expectNone('/api/coordinacion/practicas');
  });

  it('aplica filtros por tipo, estado y búsqueda', () => {
    expect(filtrarPracticas(FILAS, { q: '', tipo_practica_id: 2, estado: 'por_iniciar' })).toEqual(
      [],
    );
    expect(
      filtrarPracticas(FILAS, { q: '', tipo_practica_id: 2, estado: 'posible_abandono' }).map(
        (f) => f.id,
      ),
    ).toEqual([2]);
    expect(
      filtrarPracticas(FILAS, { q: 'fundacion', tipo_practica_id: null, estado: null }).map(
        (f) => f.id,
      ),
    ).toEqual([2]);
    expect(
      filtrarPracticas(FILAS, { q: '', tipo_practica_id: null, estado: 'en_curso' }).map(
        (f) => f.id,
      ),
    ).toEqual([3]);
  });

  it('exporta los resultados con los filtros vigentes', () => {
    const entrada = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>(
      'input[type=search]',
    )!;
    entrada.value = 'camila';
    entrada.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    (fixture.nativeElement as HTMLElement)
      .querySelector<HTMLButtonElement>('button.exportar')!
      .click();
    const peticion = http.expectOne(
      (peticion) =>
        peticion.url === '/api/coordinacion/practicas/exportar' &&
        peticion.params.get('q') === 'camila',
    );
    expect(peticion.request.responseType).toBe('blob');
    peticion.flush(
      new Blob(['x'], {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      }),
    );
    fixture.detectChanges();

    expect(URL.createObjectURL).toHaveBeenCalled();
  });
});
