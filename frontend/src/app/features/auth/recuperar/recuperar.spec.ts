import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Recuperar } from './recuperar';

describe('Recuperar', () => {
  let fixture: ComponentFixture<Recuperar>;
  let el: HTMLElement;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Recuperar],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Recuperar);
    el = fixture.nativeElement as HTMLElement;
    await fixture.whenStable();
  });

  afterEach(() => TestBed.inject(HttpTestingController).verify());

  function enviar(correo: string): void {
    const input = el.querySelector<HTMLInputElement>('input[type=email]')!;
    input.value = correo;
    input.dispatchEvent(new Event('input'));
    el.querySelector('form')!.dispatchEvent(new Event('submit'));
  }

  it('pide el enlace y muestra a qué correo se envió', async () => {
    enviar('secretaria@uta.cl');

    const peticion = TestBed.inject(HttpTestingController).expectOne('/api/recuperar-contrasena');
    expect(peticion.request.body).toEqual({ email: 'secretaria@uta.cl' });
    peticion.flush({ message: 'ok' });
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('[role=status]')?.textContent).toContain('secretaria@uta.cl');
    expect(el.querySelector('[role=status]')?.textContent).toContain('24 horas');
  });

  it('no llama a la API con un correo inválido', () => {
    enviar('no-es-correo');
    fixture.detectChanges();

    TestBed.inject(HttpTestingController).expectNone('/api/recuperar-contrasena');
    expect(el.textContent).toContain('El correo no es válido.');
  });

  it('avisa cuando hay demasiados intentos', async () => {
    enviar('secretaria@uta.cl');
    TestBed.inject(HttpTestingController).expectOne('/api/recuperar-contrasena')
      .flush({}, { status: 429, statusText: 'Too Many Requests' });
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('[role=alert]')?.textContent).toContain('Demasiados intentos');
  });
});
