import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { vi } from 'vitest';

import { AcuerdoCentro } from './acuerdo-centro';

describe('AcuerdoCentro', () => {
  let fixture: ComponentFixture<AcuerdoCentro>;
  let http: HttpTestingController;
  const boton = () => [...(fixture.nativeElement as HTMLElement).querySelectorAll('button')]
    .find((b) => b.textContent?.includes('Descargar para firmar'))!;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AcuerdoCentro],
      providers: [provideHttpClient(), provideHttpClientTesting()],
    }).compileComponents();
    fixture = TestBed.createComponent(AcuerdoCentro);
    fixture.componentRef.setInput('centroId', 4);
    http = TestBed.inject(HttpTestingController);
    fixture.detectChanges();
    await fixture.whenStable();
  });

  afterEach(() => {
    http.verify();
    vi.restoreAllMocks();
  });

  it('sin representante ni vigencia no genera el acuerdo para firmar', () => {
    boton().click();
    fixture.detectChanges();

    http.expectNone('/api/centros/4/acuerdo/borrador');
    const texto = (fixture.nativeElement as HTMLElement).textContent;
    expect(texto).toContain('Ingresa el representante legal.');
    expect(texto).toContain('Ingresa el inicio de la vigencia.');
    expect(texto).toContain('Ingresa el término de la vigencia.');
  });

  it('con tipo, representante y vigencia descarga el acuerdo para firmar sin pedir el PDF', () => {
    const crearUrl = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:acuerdo');
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined);
    const escribir = (campo: string, valor: string) => {
      const entrada = (fixture.nativeElement as HTMLElement).querySelector<HTMLInputElement>(`input[formcontrolname="${campo}"]`)!;
      entrada.value = valor;
      entrada.dispatchEvent(new Event('input'));
    };
    escribir('representante_legal', 'Ana Pérez Rojas');
    escribir('fecha_inicio', '2026-12-01');
    escribir('fecha_termino', '2028-11-30');

    boton().click();
    const peticion = http.expectOne('/api/centros/4/acuerdo/borrador');
    expect(peticion.request.body).toEqual({
      tipo: 'tradicional', representante_legal: 'Ana Pérez Rojas', fecha_inicio: '2026-12-01', fecha_termino: '2028-11-30',
    });
    peticion.flush(new Blob(['%PDF'], { type: 'application/pdf' }));

    expect(crearUrl).toHaveBeenCalled();
  });
});
