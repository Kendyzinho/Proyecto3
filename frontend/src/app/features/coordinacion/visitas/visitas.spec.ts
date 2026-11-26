import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MatDialog } from '@angular/material/dialog';
import { Router, provideRouter } from '@angular/router';
import { of } from 'rxjs';

import { AgendarVisita } from './agendar-visita';

import { Visitas } from './visitas';
import { CentroSinVisita, Visita } from './visitas-api';

const VISITAS: Visita[] = [
  {
    id: 1, fecha_hora: '2026-11-20 09:00', modalidad: 'terreno', estado: 'realizada', centro: { id: 4, nombre: 'Corfo Arica y Parinacota' },
    practicas: [{ id: 15, estudiante: 'Diego Choque', tipo_practica: 'Profesional' }], pautas: { enviadas: 0, registradas: 0, total: 1 },
  },
  {
    id: 2, fecha_hora: '2026-12-02 10:30', modalidad: 'virtual', estado: 'cancelada', centro: { id: 2, nombre: 'Productora Norte Visual' },
    practicas: [{ id: 12, estudiante: 'Pedro Lagos', tipo_practica: 'Profesional' }], pautas: { enviadas: 0, registradas: 0, total: 1 },
  },
];
const SIN_VISITA: CentroSinVisita[] = [
  { id: 2, nombre: 'Productora Norte Visual', practicas: [{ id: 11, estudiante: 'Antonia Vega', tipo_practica: 'Laboral II' }, { id: 12, estudiante: 'Pedro Lagos', tipo_practica: 'Profesional' }] },
];

describe('Visitas', () => {
  let fixture: ComponentFixture<Visitas>;
  let http: HttpTestingController;
  const texto = () => fixture.nativeElement.textContent as string;

  function crear(visitas = VISITAS, sinVisita = SIN_VISITA) {
    TestBed.configureTestingModule({ imports: [Visitas], providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])] });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(Visitas);
    responder(visitas, sinVisita);
  }

  function responder(visitas: Visita[], sinVisita: CentroSinVisita[]) {
    http.expectOne('/api/coordinacion/visitas').flush({ data: visitas });
    http.expectOne('/api/coordinacion/visitas/centros-sin-visita').flush({ data: sinVisita });
    fixture.detectChanges();
  }
  afterEach(() => {
    http.verify();
    vi.restoreAllMocks();
  });

  it('muestra los centros sin visita y la agenda con fecha, hora, modalidad y estado', () => {
    crear();

    expect(texto()).toContain('Sin visita registrada este semestre');
    expect(texto()).toContain('Antonia Vega, Pedro Lagos');
    const filas = [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row')] as HTMLElement[];
    expect(filas).toHaveLength(2);
    expect(filas[0].textContent).toContain('20/11/2026');
    expect(filas[0].textContent).toContain('09:00');
    expect(filas[0].textContent).toContain('Terreno');
    expect(filas[0].textContent).toContain('Realizada');
    // Una visita cancelada no tiene acciones
    expect(filas[1].querySelectorAll('button')).toHaveLength(0);
  });

  it('sin centros pendientes no muestra la alerta', () => {
    crear(VISITAS, []);
    expect(texto()).not.toContain('Sin visita registrada este semestre');
  });

  it('cancelar pide confirmación, llama a la API y recarga', () => {
    crear([{ ...VISITAS[0], estado: 'agendada' }]);
    vi.spyOn(window, 'confirm').mockReturnValue(true);

    const cancelar = [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row button')].find((b) => (b as HTMLElement).textContent?.includes('Cancelar')) as HTMLButtonElement;
    cancelar.click();
    http.expectOne('/api/coordinacion/visitas/1/cancelar').flush({ data: { ...VISITAS[0], estado: 'cancelada' } });
    responder([{ ...VISITAS[0], estado: 'cancelada' }], SIN_VISITA);

    expect(texto()).toContain('Cancelada');
  });

  it('si el usuario no confirma, no cancela', () => {
    crear([{ ...VISITAS[0], estado: 'agendada' }]);
    vi.spyOn(window, 'confirm').mockReturnValue(false);

    const cancelar = [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row button')].find((b) => (b as HTMLElement).textContent?.includes('Cancelar')) as HTMLButtonElement;
    cancelar.click();
    http.expectNone('/api/coordinacion/visitas/1/cancelar');
  });

  it('agendar desde la alerta abre el diálogo con el centro y sus prácticas', () => {
    crear();
    const abrir = vi.spyOn(TestBed.inject(MatDialog), 'open').mockReturnValue({ afterClosed: () => of(undefined) } as never);

    const boton = fixture.nativeElement.querySelector('.sin-visita__fila button') as HTMLButtonElement;
    boton.click();

    expect(abrir).toHaveBeenCalledWith(AgendarVisita, expect.objectContaining({ data: { centroId: 2, practicas: [11, 12] } }));
  });

  // HU-16
  it('solo las visitas realizadas tienen Registrar pauta, con su avance', () => {
    crear([VISITAS[0], { ...VISITAS[0], id: 3, estado: 'agendada' }], []);

    const filas = [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row')] as HTMLElement[];
    expect(filas[0].textContent).toContain('Pauta 0 de 1');
    expect(filas[0].textContent).toContain('Registrar pauta');
    expect(filas[1].textContent).not.toContain('Pauta');
  });

  it('con todas las pautas enviadas dice Pauta completa y abre la pauta de la visita', () => {
    crear([{ ...VISITAS[0], pautas: { enviadas: 1, registradas: 1, total: 1 } }], []);
    const navegar = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);

    expect(texto()).toContain('Pauta completa');
    const ver = [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row button')].find((b) => (b as HTMLElement).textContent?.includes('Ver pautas')) as HTMLButtonElement;
    ver.click();

    expect(navegar).toHaveBeenCalledWith([1, 'pauta'], expect.anything());
  });

  it('una visita con pautas registradas no ofrece Cancelar', () => {
    crear([{ ...VISITAS[0], pautas: { enviadas: 0, registradas: 1, total: 1 } }], []);

    const botones = [...fixture.nativeElement.querySelectorAll('tr.mat-mdc-row button')].map((b) => (b as HTMLElement).textContent?.trim());
    expect(botones).toContain('Editar');
    expect(botones).not.toContain('Cancelar');
  });
});
