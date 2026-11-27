import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Documentos } from './documentos';
import { DetallePractica, PracticaPorFormalizar, claseEstado, textoEstado } from './documentos-api';

const JOSEFA: PracticaPorFormalizar = {
  id: 3, estudiante: 'Josefa Ramírez Tapia', correo: 'josefa.ramirez@alumnos.uta.cl', practica: 'Profesional',
  centro: 'Fundación UTA', supervisor: 'Carolina Mamani Ticona', correo_supervisor: 'cmamani@fundacionuta.cl', con_acuerdo: true,
  carta: { estado: 'por_generar', documento_id: null }, seguro: { estado: 'por_generar', documento_id: null },
};
const DIEGO: PracticaPorFormalizar = {
  ...JOSEFA, id: 5, estudiante: 'Diego Choque Vargas', centro: 'Corfo Arica y Parinacota', con_acuerdo: false,
  carta: { estado: 'sin_acuerdo', documento_id: null }, seguro: { estado: 'en_espera', documento_id: null },
};
const DETALLE: DetallePractica = {
  ...DIEGO,
  textos: {
    carta: { titulo: 'Carta de presentación', lugar_fecha: 'Arica, 17 de octubre de 2026', destinatario: [], parrafos: ['Presento a Diego'], firma: ['Jefe', 'Jefe de Carrera - Diseño Multimedia', 'Universidad de Tarapacá'], pie: 'Avda. 18 de septiembre 2222' },
    seguro: { titulo: 'Guía del seguro escolar', lugar_fecha: 'Arica, 17 de octubre de 2026', datos: {}, secciones: [], pie: 'Avda. 18 de septiembre 2222' },
  },
  centro_id: 4, centro_interno: false,
  acuerdo: { id: 4, tipo: 'tradicional', representante_legal: 'María', fecha_inicio: null, fecha_termino: null, estado: 'en_tramite', tiene_archivo: false },
  renovacion: null,
};

describe('Documentos', () => {
  let fixture: ComponentFixture<Documentos>;
  let http: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Documentos],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();
    fixture = TestBed.createComponent(Documentos);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    http.expectOne('/api/periodos/actual').flush({ id: 2, nombre: '2º semestre 2026' });
    http.expectOne((r) => r.url === '/api/documentos' && r.params.get('periodo_id') === '2').flush({ data: [DIEGO, JOSEFA] });
    http.expectOne('/api/practicas/5/documentos').flush(DETALLE);
    fixture.detectChanges();
    await fixture.whenStable();
  });

  afterEach(() => http.verify());

  it('lista las prácticas con el estado de su carta y su seguro', () => {
    const texto = (fixture.nativeElement as HTMLElement).textContent ?? '';
    expect(texto).toContain('Prácticas por formalizar');
    expect(texto).toContain('Josefa Ramírez Tapia');
    expect(texto).toContain('Sin acuerdo');
    expect(texto).toContain('En espera');
  });

  it('sin acuerdo vigente muestra la franja para completarlo y oculta la carta, el seguro y el envío', () => {
    const el = fixture.nativeElement as HTMLElement;
    const botones = [...el.querySelectorAll('button')].map((b) => b.textContent?.trim() ?? '');
    expect(el.querySelector('.franja-acuerdo--bloqueo')?.textContent).toContain('El acuerdo con Corfo Arica y Parinacota está en trámite');
    expect(botones).toContain('Completar acuerdo');
    expect(el.querySelector('mat-tab-group')).toBeNull();
    expect(botones.some((texto) => texto.includes('Enviar'))).toBe(false);
  });

  it('con acuerdo vigente muestra su término arriba y solo las pestañas de carta y seguro', async () => {
    const el = fixture.nativeElement as HTMLElement;
    [...el.querySelectorAll<HTMLButtonElement>('.lista__fila')].find((b) => b.textContent?.includes('Josefa'))!.click();
    http.expectOne('/api/practicas/3/documentos').flush({
      ...DETALLE, ...JOSEFA,
      acuerdo: { ...DETALLE.acuerdo!, fecha_inicio: '2026-03-01', fecha_termino: '2027-12-31', estado: 'vigente', tiene_archivo: true },
    });
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('.franja-acuerdo')?.textContent).toContain('Acuerdo con Fundación UTA vigente hasta el 31-12-2027');
    expect(el.querySelector('.franja-acuerdo--bloqueo')).toBeNull();
    const pestanas = [...el.querySelectorAll('[role="tab"]')].map((t) => t.textContent?.trim());
    expect(pestanas).toEqual(['Carta de presentación', 'Seguro escolar']);
    expect([...el.querySelectorAll('button')].some((b) => b.textContent?.includes('Enviar al estudiante y al centro'))).toBe(true);
  });

  // HU-10: lo firmado no se edita; se renueva sin tocar el acuerdo vigente
  it('un acuerdo vigente no se edita, se renueva, y muestra la renovación cargada', async () => {
    const el = fixture.nativeElement as HTMLElement;
    const vigente = { ...DETALLE.acuerdo!, fecha_inicio: '2026-03-01', fecha_termino: '2027-12-31', estado: 'vigente' as const, tiene_archivo: true };
    const botones = () => [...el.querySelectorAll('.franja-acuerdo button')].map((b) => b.textContent?.trim());
    const abrir = async (detalle: DetallePractica) => {
      [...el.querySelectorAll<HTMLButtonElement>('.lista__fila')].find((b) => b.textContent?.includes('Josefa'))!.click();
      http.expectOne('/api/practicas/3/documentos').flush(detalle);
      fixture.detectChanges();
      await fixture.whenStable();
    };

    await abrir({ ...DETALLE, ...JOSEFA, acuerdo: vigente });
    expect(botones()).toEqual(['Ver PDF', 'Renovar']);

    await abrir({ ...DETALLE, ...JOSEFA, acuerdo: vigente, renovacion: { ...vigente, id: 9, fecha_inicio: '2028-01-01', fecha_termino: '2030-12-31', estado: 'programado' } });
    expect(el.querySelector('.franja-acuerdo')?.textContent).toContain('Renovación firmada, rige desde el 01-01-2028.');
    expect(botones()).toEqual(['Ver PDF', 'Ver renovación']);
  });

  it('un acuerdo firmado que todavía no rige no se presenta como en trámite', async () => {
    const el = fixture.nativeElement as HTMLElement;
    [...el.querySelectorAll<HTMLButtonElement>('.lista__fila')].find((b) => b.textContent?.includes('Josefa'))!.click();
    http.expectOne('/api/practicas/3/documentos').flush({
      ...DETALLE, ...JOSEFA, con_acuerdo: false,
      acuerdo: { ...DETALLE.acuerdo!, fecha_inicio: '2027-01-01', fecha_termino: '2027-12-31', estado: 'programado', tiene_archivo: true },
    });
    fixture.detectChanges();
    await fixture.whenStable();

    const franja = el.querySelector('.franja-acuerdo')?.textContent ?? '';
    expect(franja).toContain('está firmado y rige desde el 01-01-2027');
    expect(franja).not.toContain('en trámite');
    expect(franja).toContain('Editar acuerdo');
  });

  it('usa el género de cada documento en los estados', () => {
    expect(textoEstado('enviada', 'carta')).toBe('Enviada');
    expect(textoEstado('enviada', 'seguro')).toBe('Enviado');
    expect(claseEstado('generada')).toBe('estado--info');
  });
});
