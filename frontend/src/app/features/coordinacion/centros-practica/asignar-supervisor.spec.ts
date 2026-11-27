import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { MAT_DIALOG_DATA, MatDialogRef } from '@angular/material/dialog';

import { AsignarSupervisor } from './asignar-supervisor';
import { CentroPractica } from './centro-practica.model';

const CENTRO = { id: 7, nombre: 'Productora Norte Visual', estado: 'Activo', esInterno: false, acuerdo: 'Vigente' } as CentroPractica;
const BUSCAR = '/api/centros-practica/7/supervisores/cuentas';
const REVISAR_CORREO = '/api/centros-practica/7/supervisores/cuenta';
const ASIGNAR = '/api/centros-practica/7/supervisores/asignar';
const CENTRO_API = { id: 7, supervisores: [], acuerdos: [], requerimientos: [], estudiantes: [], areas: [] };

describe('AsignarSupervisor', () => {
  let fixture: ComponentFixture<AsignarSupervisor>;
  let http: HttpTestingController;
  const cerrar = vi.fn();
  const raiz = () => fixture.nativeElement as HTMLElement;
  const texto = () => raiz().textContent ?? '';
  const guardar = () => raiz().querySelector<HTMLButtonElement>('button[type="submit"]')!;
  const botonCon = (texto: string) => Array.from(raiz().querySelectorAll<HTMLButtonElement>('button')).find((b) => b.textContent?.includes(texto))!;
  const esperar = (ms: number) => new Promise((listo) => setTimeout(listo, ms));

  beforeEach(() => {
    cerrar.mockReset();
    TestBed.configureTestingModule({
      imports: [AsignarSupervisor],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: MAT_DIALOG_DATA, useValue: CENTRO },
        { provide: MatDialogRef, useValue: { close: cerrar } },
      ],
    });
    http = TestBed.inject(HttpTestingController);
    fixture = TestBed.createComponent(AsignarSupervisor);
    fixture.detectChanges();
  });

  afterEach(() => http.verify());

  function escribir(selector: string, valor: string) {
    const input = raiz().querySelector<HTMLInputElement>(selector)!;
    input.value = valor;
    input.dispatchEvent(new Event('input'));
    fixture.detectChanges();
  }

  async function buscar(valor: string, resultados: object[]) {
    escribir('input[type="search"]', valor);
    await esperar(300);
    http.expectOne((r) => r.url === BUSCAR && r.params.get('q') === valor).flush({ data: resultados });
    fixture.detectChanges();
  }

  async function revisarCorreo(correo: string, respuesta: object) {
    escribir('input[formcontrolname="correo"]', correo);
    await esperar(350);
    http.expectOne((r) => r.url === REVISAR_CORREO).flush({ data: respuesta });
    fixture.detectChanges();
  }

  it('busca por nombre y asigna la cuenta elegida sin pedir el nombre', async () => {
    await buscar('wilson', [
      { nombre: 'Wilson Pérez', correo: 'wperez@example.test', activa: true, en_este_centro: false },
      { nombre: 'Wilson Rojas', correo: 'wrojas@example.test', activa: true, en_este_centro: true },
    ]);
    expect(texto()).toContain('Ya está en este centro');
    expect(botonCon('Wilson Rojas').disabled).toBe(true);

    botonCon('Wilson Pérez').click();
    fixture.detectChanges();
    expect(texto()).toContain('se asignará a este centro');
    expect(raiz().querySelector('input[formcontrolname="nombre"]')).toBeNull();
    expect(guardar().textContent).toContain('Asignar al centro');

    guardar().click();
    const peticion = http.expectOne(ASIGNAR);
    expect(peticion.request.body).toEqual({ correo: 'wperez@example.test', nombre: null, cargo: null, unidad: null, telefono: null });
    peticion.flush({ data: CENTRO_API, message: 'Supervisor(a) asignado al centro con su cuenta existente.' });
    expect(cerrar).toHaveBeenCalledWith(expect.objectContaining({ mensaje: 'Supervisor(a) asignado al centro con su cuenta existente.' }));
  });

  it('si no lo encuentra, lo crea con el nombre buscado', async () => {
    await buscar('Marta Soto', []);
    expect(texto()).toContain('No hay cuentas de supervisor(a)');

    botonCon('Es un supervisor(a) nuevo').click();
    fixture.detectChanges();
    expect(raiz().querySelector<HTMLInputElement>('input[formcontrolname="nombre"]')!.value).toBe('Marta Soto');

    await revisarCorreo('marta@example.test', { cuenta: null, interna: false, en_este_centro: false });
    expect(guardar().textContent).toContain('Crear y asignar');
    guardar().click();
    expect(http.expectOne(ASIGNAR).request.body).toEqual(expect.objectContaining({ correo: 'marta@example.test', nombre: 'Marta Soto' }));
  });

  it('al crear, un correo que ya tiene cuenta pasa a asignar esa cuenta', async () => {
    botonCon('Es un supervisor(a) nuevo').click();
    fixture.detectChanges();

    await revisarCorreo('wperez@example.test', { cuenta: { nombre: 'Wilson Pérez', activa: true }, interna: false, en_este_centro: false });

    expect(texto()).toContain('Wilson Pérez');
    expect(guardar().textContent).toContain('Asignar al centro');
  });

  it('no deja crear con el correo de una cuenta de la universidad', async () => {
    botonCon('Es un supervisor(a) nuevo').click();
    fixture.detectChanges();

    await revisarCorreo('coordinadora@uta.cl', { cuenta: null, interna: true, en_este_centro: false });

    expect(texto()).toContain('Ese correo es de una cuenta de la universidad');
    expect(guardar().disabled).toBe(true);
  });
});
