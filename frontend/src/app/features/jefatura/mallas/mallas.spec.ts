import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Mallas } from './mallas';
import { Malla } from './mallas-api';

const MALLAS: Malla[] = [
  {
    id: 1, nombre: 'Malla actual', vigente_desde: '2018-03-01', estado: 'vigente',
    practicas: [
      { id: 1, malla_id: 1, nombre: 'Laboral I', categoria: 'laboral', horas_requeridas: 64, prerrequisito: 'Primer año aprobado' },
      { id: 2, malla_id: 1, nombre: 'Laboral II', categoria: 'laboral', horas_requeridas: 64, prerrequisito: 'Laboral I aprobada' },
      { id: 3, malla_id: 1, nombre: 'Profesional', categoria: 'profesional', horas_requeridas: 320, prerrequisito: 'Laboral II aprobada' },
    ],
  },
  {
    id: 2, nombre: 'Malla nueva', vigente_desde: null, estado: 'en_preparacion',
    practicas: [
      { id: 4, malla_id: 2, nombre: 'Laboral', categoria: 'laboral', horas_requeridas: 96, prerrequisito: 'Primer año aprobado' },
      { id: 5, malla_id: 2, nombre: 'Profesional', categoria: 'profesional', horas_requeridas: 240, prerrequisito: 'Laboral aprobada' },
    ],
  },
];

describe('Mallas', () => {
  let fixture: ComponentFixture<Mallas>;
  let http: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Mallas],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();

    fixture = TestBed.createComponent(Mallas);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/mallas').flush({ data: MALLAS });
    fixture.detectChanges();
    await fixture.whenStable();
  });

  afterEach(() => http.verify());

  function elemento(): HTMLElement {
    return fixture.nativeElement as HTMLElement;
  }

  function botones(texto: string): HTMLButtonElement[] {
    return Array.from(elemento().querySelectorAll<HTMLButtonElement>('button')).filter((b) => b.textContent?.includes(texto));
  }

  it('muestra cada malla con sus prácticas y horas', () => {
    const texto = elemento().textContent ?? '';
    expect(elemento().querySelectorAll('tr.mat-mdc-row').length).toBe(5);
    expect(texto).toContain('320 h');
    expect(texto).toContain('Vigente');
    expect(texto).toContain('En preparación');
  });

  it('solo deja agregar prácticas y publicar en la malla en preparación', () => {
    expect(botones('Agregar práctica').length).toBe(1);
    expect(botones('Publicar malla').length).toBe(1);
  });

  it('no deja crear otra malla mientras haya una en preparación', () => {
    expect(botones('Nueva malla')[0].disabled).toBe(true);
  });
});
