import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { ItemInstrumento } from '../../../core/models/instrumento';
import { Informe } from '../../coordinacion/informes/informes-api';
import { InformeSupervisor } from './informe-supervisor';

const ESCALA = ['Totalmente en desacuerdo', 'En desacuerdo', 'Ni de acuerdo ni en desacuerdo', 'De acuerdo', 'Totalmente de acuerdo'];

function escala(id: number, seccion: string, enunciado: string): ItemInstrumento {
  return {
    id, clave: `i${id}`, enunciado, tipo: 'escala', obligatorio: true, seccion,
    opciones: [...ESCALA.map((etiqueta, i) => ({ id: id * 10 + i + 1, etiqueta, valor: i + 1 })), { id: id * 10 + 6, etiqueta: 'No aplica', valor: null }],
  };
}

// Un criterio por sección y el comentario opcional (el real tiene 12 transversales y 5 o 9 disciplinares)
const ITEMS: ItemInstrumento[] = [
  escala(1, 'transversal', 'Puntualidad coherente con los horarios programados'),
  escala(2, 'disciplinar', 'Ilustraciones digitales con calidad'),
  { id: 3, clave: 'comentario', enunciado: 'Comentario sobre el desempeño del estudiante', tipo: 'texto', obligatorio: false, seccion: 'cierre', opciones: [] },
];

function informe(enviada_en: string | null = null, respuestas: Informe['informe']['respuestas'] = []): Informe {
  return {
    practica: { id: 11, estudiante: 'Antonia Vega', tipo_practica: 'Laboral II', centro: 'Productora Norte Visual', supervisor: 'Sergio Paredes', fecha_fin: '2026-10-02' },
    informe: { plantilla_id: 4, items: ITEMS, respuesta_id: 8, enviada_en, respuestas },
  };
}

describe('InformeSupervisor', () => {
  let fixture: ComponentFixture<InformeSupervisor>;
  let http: HttpTestingController;
  const texto = () => fixture.nativeElement.textContent as string;
  const boton = (nombre: string) =>
    [...fixture.nativeElement.querySelectorAll('button')].find((b) => (b as HTMLElement).textContent?.includes(nombre)) as HTMLButtonElement | undefined;

  function crear(datos = informe()) {
    TestBed.configureTestingModule({ imports: [InformeSupervisor], providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(InformeSupervisor);
    fixture.componentRef.setInput('practica', '11');
    fixture.detectChanges();
    http.expectOne('/api/supervisor/informes/11').flush({ data: datos });
    fixture.detectChanges();
  }

  function marcar(criterio: number, etiqueta: string) {
    const grupo = fixture.nativeElement.querySelectorAll('mat-button-toggle-group')[criterio] as HTMLElement;
    const opcion = [...grupo.querySelectorAll('button')].find((b) => b.textContent?.trim() === etiqueta) as HTMLButtonElement;
    opcion.click();
    fixture.detectChanges();
  }

  afterEach(() => {
    http.verify();
    vi.restoreAllMocks();
  });

  it('muestra el estudiante, la escala y las secciones del formulario oficial', () => {
    crear();

    expect(texto()).toContain('Antonia Vega');
    expect(texto()).toContain('Totalmente de acuerdo');
    expect(texto()).toContain('Habilidades transversales');
    expect(texto()).toContain('Habilidades disciplinares');
    const botones = [...fixture.nativeElement.querySelectorAll('mat-button-toggle-group')[0].querySelectorAll('button')] as HTMLElement[];
    expect(botones.map((b) => b.textContent?.trim())).toEqual(['1', '2', '3', '4', '5', 'No aplica']);
    expect(botones[3].getAttribute('aria-label')).toBe('De acuerdo');
  });

  it('guardar borrador manda lo marcado con enviar en falso', () => {
    crear();
    marcar(0, '4');

    boton('Guardar borrador')!.click();
    const peticion = http.expectOne('/api/supervisor/informes/11');
    expect(peticion.request.method).toBe('PUT');
    expect(peticion.request.body).toEqual({ respuestas: [{ item_id: 1, opcion_id: 14 }], enviar: false });
    peticion.flush({ data: informe(null, [{ item_id: 1, opcion_id: 14, texto: null }]) });
  });

  it('enviar pide confirmación y después se ve en solo lectura', () => {
    crear();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    marcar(0, '5');
    marcar(1, 'No aplica');

    boton('Enviar informe')!.click();
    const peticion = http.expectOne('/api/supervisor/informes/11');
    expect(peticion.request.body).toEqual({ respuestas: [{ item_id: 1, opcion_id: 15 }, { item_id: 2, opcion_id: 26 }], enviar: true });
    peticion.flush({ data: informe('2026-11-26T10:00:00-03:00', [{ item_id: 1, opcion_id: 15, texto: null }, { item_id: 2, opcion_id: 26, texto: null }]) });
    fixture.detectChanges();

    expect(texto()).toContain('26/11/2026');
    expect(fixture.nativeElement.querySelector('mat-button-toggle-group')).toBeNull();
    expect(boton('Enviar informe')).toBeUndefined();
    expect(texto()).toContain('Totalmente de acuerdo');
    expect(texto()).toContain('No aplica');
    expect(texto()).toContain('Sin comentario');
  });

  it('si no confirma, no envía', () => {
    crear();
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    boton('Enviar informe')!.click();
    http.expectNone('/api/supervisor/informes/11');
  });

  it('marca lo que falta responder', () => {
    crear();
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    boton('Enviar informe')!.click();
    http.expectOne('/api/supervisor/informes/11').flush(
      { message: 'Faltan', errors: { respuestas: ['Responde todas las preguntas obligatorias antes de enviar.'], faltantes: ['1', '2'] } },
      { status: 422, statusText: 'Unprocessable Entity' },
    );
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelectorAll('.criterio--falta')).toHaveLength(2);
    expect(texto()).toContain('Responde todas las preguntas obligatorias antes de enviar.');
  });

  it('un informe que no le pidieron muestra un aviso', () => {
    TestBed.configureTestingModule({ imports: [InformeSupervisor], providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(InformeSupervisor);
    fixture.componentRef.setInput('practica', '99');
    fixture.detectChanges();
    http.expectOne('/api/supervisor/informes/99').flush({ message: 'No encontrado' }, { status: 404, statusText: 'Not Found' });
    fixture.detectChanges();

    expect(texto()).toContain('Este informe no existe o no te lo han solicitado.');
  });
});
