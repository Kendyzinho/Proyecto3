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
    carta: { unidad: 'Escuela', lugar_fecha: 'Arica, 17 de octubre de 2026', destinatario: [], parrafos: ['Presento a Diego'], firma: ['Jefe', 'Jefe(a) de Carrera'] },
    seguro: { unidad: 'Escuela', titulo: 'Guía del seguro escolar', datos: {}, secciones: [] },
  },
  centro_id: 4, centro_interno: false,
  acuerdo: { id: 4, tipo: 'tradicional', representante_legal: 'María', fecha_inicio: null, fecha_termino: null, estado: 'en_tramite', tiene_archivo: false },
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

  it('sin acuerdo vigente abre la pestaña del acuerdo y no ofrece enviar', () => {
    const el = fixture.nativeElement as HTMLElement;
    expect(el.textContent).toContain('Sin acuerdo firmado');
    const enviar = [...el.querySelectorAll('button')].find((b) => b.textContent?.includes('Enviar'));
    expect(enviar).toBeUndefined();
  });

  it('usa el género de cada documento en los estados', () => {
    expect(textoEstado('enviada', 'carta')).toBe('Enviada');
    expect(textoEstado('enviada', 'seguro')).toBe('Enviado');
    expect(claseEstado('generada')).toBe('estado--info');
  });
});
