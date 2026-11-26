import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { PautaVisita } from './pauta-visita';
import { FormularioInstrumento, PautasVisita, RespuestaGuardada } from './visitas-api';

// Dos criterios de escala y el comentario obligatorio (la plantilla real tiene 6 criterios)
const FORMULARIO: FormularioInstrumento = {
  plantilla_id: 1,
  items: [
    { id: 1, clave: 'condiciones', enunciado: 'Condiciones de trabajo', tipo: 'escala', obligatorio: true, seccion: 'pauta', opciones: [1, 2, 3, 4, 5].map((n) => ({ id: 10 + n, etiqueta: String(n), valor: n })) },
    { id: 2, clave: 'pertinencia', enunciado: 'Tareas pertinentes', tipo: 'escala', obligatorio: true, seccion: 'pauta', opciones: [1, 2, 3, 4, 5].map((n) => ({ id: 20 + n, etiqueta: String(n), valor: n })) },
    { id: 3, clave: 'comentarios', enunciado: 'Comentarios de la visita', tipo: 'texto', obligatorio: true, seccion: 'pauta', opciones: [] },
  ],
};

const ENVIADA: RespuestaGuardada = {
  ...FORMULARIO, respuesta_id: 9, enviada_en: '2026-11-24T18:00:00-03:00',
  respuestas: [{ item_id: 1, opcion_id: 14, texto: null }, { item_id: 2, opcion_id: 25, texto: null }, { item_id: 3, opcion_id: null, texto: 'Muy buen ambiente.' }],
};

function datos(antonia: RespuestaGuardada | null = null): PautasVisita {
  return {
    visita: {
      id: 5, fecha_hora: '2026-11-24 15:00', modalidad: 'terreno', estado: 'realizada', centro: { id: 2, nombre: 'Productora Norte Visual' },
      practicas: [], pautas: { enviadas: 0, total: 2 },
    },
    formulario: FORMULARIO,
    practicas: [
      { id: 11, estudiante: 'Antonia Vega', tipo_practica: 'Laboral II', respuesta: antonia },
      { id: 12, estudiante: 'Pedro Lagos', tipo_practica: 'Profesional', respuesta: null },
    ],
  };
}

describe('PautaVisita', () => {
  let fixture: ComponentFixture<PautaVisita>;
  let http: HttpTestingController;
  const tarjetas = () => [...fixture.nativeElement.querySelectorAll('section.pauta')] as HTMLElement[];
  const boton = (tarjeta: HTMLElement, texto: string) =>
    [...tarjeta.querySelectorAll('button')].find((b) => b.textContent?.includes(texto)) as HTMLButtonElement;

  function crear(respuesta = datos()) {
    TestBed.configureTestingModule({ imports: [PautaVisita], providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(PautaVisita);
    fixture.componentRef.setInput('id', '5');
    fixture.detectChanges();
    http.expectOne('/api/coordinacion/visitas/5/pautas').flush({ data: respuesta });
    fixture.detectChanges();
  }

  // Marca una nota en un criterio de la tarjeta
  function marcar(tarjeta: HTMLElement, criterio: number, nota: string) {
    const grupo = tarjeta.querySelectorAll('mat-button-toggle-group')[criterio];
    const opcion = [...grupo.querySelectorAll('button')].find((b) => b.textContent?.trim() === nota) as HTMLButtonElement;
    opcion.click();
    fixture.detectChanges();
  }

  function comentar(tarjeta: HTMLElement, texto: string) {
    const campo = tarjeta.querySelector('textarea') as HTMLTextAreaElement;
    campo.value = texto;
    campo.dispatchEvent(new Event('input'));
    fixture.detectChanges();
  }

  afterEach(() => {
    http.verify();
    vi.restoreAllMocks();
  });

  it('muestra la visita y una pauta por estudiante con sus criterios 1 a 5', () => {
    crear();

    const texto = fixture.nativeElement.textContent as string;
    expect(texto).toContain('Productora Norte Visual');
    expect(texto).toContain('24/11/2026');
    expect(tarjetas()).toHaveLength(2);
    expect(tarjetas()[0].textContent).toContain('Antonia Vega');
    expect(tarjetas()[0].querySelectorAll('mat-button-toggle-group')).toHaveLength(2);
    expect(tarjetas()[0].querySelectorAll('mat-button-toggle')).toHaveLength(10);
    expect(tarjetas()[0].querySelector('textarea')).not.toBeNull();
  });

  it('guardar borrador manda lo marcado con enviar en falso', () => {
    crear();
    const pedro = tarjetas()[1];
    marcar(pedro, 0, '4');

    boton(pedro, 'Guardar borrador').click();
    const peticion = http.expectOne('/api/coordinacion/visitas/5/pautas/12');

    expect(peticion.request.method).toBe('PUT');
    expect(peticion.request.body).toEqual({ respuestas: [{ item_id: 1, opcion_id: 14 }], enviar: false });
    peticion.flush({ data: { ...FORMULARIO, respuesta_id: 3, enviada_en: null, respuestas: [{ item_id: 1, opcion_id: 14, texto: null }] } });
  });

  it('enviar pide confirmación y manda todo con enviar en verdadero', () => {
    crear();
    const confirmar = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const pedro = tarjetas()[1];
    marcar(pedro, 0, '4');
    marcar(pedro, 1, '5');
    comentar(pedro, '  Muy buen ambiente.  ');

    boton(pedro, 'Enviar pauta').click();
    expect(confirmar).toHaveBeenCalled();
    const peticion = http.expectOne('/api/coordinacion/visitas/5/pautas/12');
    expect(peticion.request.body).toEqual({
      respuestas: [{ item_id: 1, opcion_id: 14 }, { item_id: 2, opcion_id: 25 }, { item_id: 3, texto: 'Muy buen ambiente.' }],
      enviar: true,
    });
    peticion.flush({ data: ENVIADA });
    fixture.detectChanges();

    expect(tarjetas()[1].textContent).toContain('Enviada el 24/11/2026');
    expect(boton(tarjetas()[1], 'Enviar pauta')).toBeUndefined();
  });

  it('si no confirma, no envía', () => {
    crear();
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    boton(tarjetas()[1], 'Enviar pauta').click();
    http.expectNone('/api/coordinacion/visitas/5/pautas/12');
  });

  it('una pauta enviada se ve en solo lectura', () => {
    crear(datos(ENVIADA));
    const antonia = tarjetas()[0];

    expect(antonia.textContent).toContain('Enviada el 24/11/2026');
    expect(antonia.querySelector('mat-button-toggle-group')).toBeNull();
    expect(antonia.querySelector('textarea')).toBeNull();
    expect(antonia.textContent).toContain('Muy buen ambiente.');
    expect(boton(antonia, 'Guardar borrador')).toBeUndefined();
  });

  it('marca los criterios que el servidor dice que faltan', () => {
    crear();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const pedro = tarjetas()[1];
    marcar(pedro, 0, '3');

    boton(pedro, 'Enviar pauta').click();
    http.expectOne('/api/coordinacion/visitas/5/pautas/12').flush(
      { message: 'Faltan respuestas', errors: { respuestas: ['Responde todas las preguntas obligatorias antes de enviar.'], faltantes: ['2', '3'] } },
      { status: 422, statusText: 'Unprocessable Entity' },
    );
    fixture.detectChanges();

    expect(tarjetas()[1].querySelectorAll('.criterio--falta')).toHaveLength(2);
    expect(tarjetas()[1].textContent).toContain('Responde todas las preguntas obligatorias antes de enviar.');
  });
});
