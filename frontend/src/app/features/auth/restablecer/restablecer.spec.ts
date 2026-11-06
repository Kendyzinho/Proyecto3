import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';

import { Restablecer } from './restablecer';

describe('Restablecer', () => {
  let fixture: ComponentFixture<Restablecer>;
  let el: HTMLElement;

  async function crear(parametros: Record<string, string>): Promise<void> {
    await TestBed.configureTestingModule({
      imports: [Restablecer],
      providers: [
        provideHttpClient(), provideHttpClientTesting(), provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: convertToParamMap(parametros) } } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Restablecer);
    el = fixture.nativeElement as HTMLElement;
    await fixture.whenStable();
  }

  afterEach(() => TestBed.inject(HttpTestingController).verify());

  function escribir(clave: string, confirmacion: string): void {
    const [nueva, repetida] = el.querySelectorAll<HTMLInputElement>('input');
    nueva.value = clave;
    nueva.dispatchEvent(new Event('input'));
    repetida.value = confirmacion;
    repetida.dispatchEvent(new Event('input'));
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
    fixture.detectChanges();
  }

  it('sin token en el enlace ofrece pedir uno nuevo', async () => {
    await crear({ email: 'jefe@uta.cl' });

    expect(el.querySelector('[role=alert]')?.textContent).toContain('El enlace no es válido');
    expect(el.querySelector('a[href="/auth/recuperar"]')).not.toBeNull();
  });

  it('envía el token, el correo y la clave nueva, y confirma el cambio', async () => {
    await crear({ token: 'abc123', email: 'jefe@uta.cl' });
    expect(el.textContent).toContain('jefe@uta.cl');

    escribir('NuevaClave1', 'NuevaClave1');
    const peticion = TestBed.inject(HttpTestingController).expectOne('/api/restablecer-contrasena');
    expect(peticion.request.body).toEqual({
      token: 'abc123', email: 'jefe@uta.cl', password: 'NuevaClave1', password_confirmation: 'NuevaClave1',
    });
    peticion.flush(null, { status: 204, statusText: 'No Content' });
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('[role=status]')?.textContent).toContain('Contraseña actualizada');
  });

  it('valida la complejidad y la confirmación antes de llamar a la API', async () => {
    await crear({ token: 'abc123', email: 'jefe@uta.cl' });

    escribir('sinmayuscula1', 'otra');
    TestBed.inject(HttpTestingController).expectNone('/api/restablecer-contrasena');
    expect(el.textContent).toContain('Incluye una letra mayúscula y un número.');
    expect(el.textContent).toContain('Las contraseñas no coinciden.');
  });

  it('si el enlace venció o ya se usó, lo dice y ofrece uno nuevo', async () => {
    await crear({ token: 'usado', email: 'jefe@uta.cl' });

    escribir('NuevaClave1', 'NuevaClave1');
    TestBed.inject(HttpTestingController).expectOne('/api/restablecer-contrasena').flush(
      { message: 'x', errors: { token: ['El enlace no es válido o ya venció. Pide uno nuevo.'] } },
      { status: 422, statusText: 'Unprocessable' },
    );
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('[role=alert]')?.textContent).toContain('venció o ya se usó');
    expect(el.querySelector('form')).toBeNull();
  });
});
