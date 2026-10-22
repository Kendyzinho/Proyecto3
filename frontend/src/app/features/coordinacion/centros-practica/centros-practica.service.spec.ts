import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { firstValueFrom } from 'rxjs';

import { CentroPractica } from './centro-practica.model';
import { CentrosPracticaService } from './centros-practica.service';

describe('CentrosPracticaService', () => {
  let service: CentrosPracticaService;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(CentrosPracticaService);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('carga centros, áreas y períodos desde los endpoints autenticados', async () => {
    const resultado = firstValueFrom(service.listar());

    http.expectOne('/api/centros-practica').flush({ data: [centroApi()] });
    http.expectOne('/api/areas').flush({ data: [{ id: 4, nombre: 'Web' }] });
    http.expectOne('/api/periodos').flush({ data: [{ id: 7, anio: 2026, semestre: 2 }] });

    const catalogo = await resultado;
    expect(catalogo.centros[0].nombre).toBe('Productora Norte');
    expect(catalogo.centros[0].estado).toBe('Propuesto');
    expect(catalogo.areas[0].nombre).toBe('Web');
    expect(catalogo.periodos[0].id).toBe(7);
  });

  it('crea un centro usando IDs de área y período del backend', async () => {
    const guardando = firstValueFrom(service.guardar(centroFormulario()));

    http.expectOne('/api/areas').flush({ data: [{ id: 4, nombre: 'Web' }] });
    http.expectOne('/api/periodos').flush({ data: [{ id: 7, anio: 2026, semestre: 2 }] });
    const crear = http.expectOne('/api/centros-practica');
    expect(crear.request.method).toBe('POST');
    expect(crear.request.body.area_ids).toEqual([4]);
    expect(crear.request.body.requerimientos).toEqual([
      { periodo_id: 7, categoria: 'laboral', cupos: 3, area_ids: [4] },
    ]);
    crear.flush({ data: centroApi({ id: 22, fecha_alta: null }) });

    expect((await guardando).id).toBe(22);
  });

  it('manda el motivo obligatorio al endpoint de baja y refleja la respuesta', async () => {
    const baja = firstValueFrom(service.darDeBaja(22, 'Evaluación desfavorable'));
    const request = http.expectOne('/api/centros-practica/22/dar-de-baja');
    expect(request.request.method).toBe('POST');
    expect(request.request.body).toEqual({ motivo_baja: 'Evaluación desfavorable' });
    request.flush({
      data: centroApi({ id: 22, estado: 'dado_de_baja', motivo_baja: 'Evaluación desfavorable' }),
    });

    const centro = await baja;
    expect(centro.estado).toBe('Dado de baja');
    expect(centro.motivoBaja).toBe('Evaluación desfavorable');
  });
});

function centroApi(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    nombre: 'Productora Norte',
    razon_social: 'Productora Norte SpA',
    rut: '12345678-5',
    direccion: 'Arica',
    telefono: '+56 9 1234 5678',
    es_interno: false,
    fecha_alta: null,
    fecha_baja: null,
    motivo_baja: null,
    estado: 'propuesto',
    estado_acuerdo: 'sin_acuerdo',
    areas: [{ id: 4, nombre: 'Web' }],
    acuerdos: [],
    requerimientos: [],
    supervisores: [],
    estudiantes: [],
    ...overrides,
  };
}

function centroFormulario(): CentroPractica {
  return {
    id: 0,
    nombre: 'Productora Norte',
    razonSocial: 'Productora Norte SpA',
    rut: '12.345.678-5',
    areas: ['Web'],
    acuerdo: 'Sin acuerdo',
    cupos: [{ periodo: '2° semestre 2026', periodoId: 7, categoria: 'Laboral', total: 3, ocupados: 0, libres: 3 }],
    supervisorPrincipal: null,
    estado: 'Propuesto',
    direccion: 'Arica',
    telefono: '+56 9 1234 5678',
    esInterno: false,
    representanteLegal: null,
    tipoAcuerdo: null,
    vigenciaAcuerdo: null,
    supervisores: [],
    estudiantes: [],
    fechaAlta: null,
  };
}
