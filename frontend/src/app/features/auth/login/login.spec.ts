import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { Login } from './login';

describe('Login', () => {
  let fixture: ComponentFixture<Login>;

  beforeEach(async () => {
    localStorage.clear();
    await TestBed.configureTestingModule({
      imports: [Login],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Login);
    await fixture.whenStable();
  });

  it('muestra el error de credenciales cuando la API responde 422', async () => {
    const el = fixture.nativeElement as HTMLElement;
    const correo = el.querySelector<HTMLInputElement>('input[type=email]')!;
    const clave = el.querySelector<HTMLInputElement>('input[type=password]')!;
    correo.value = 'coordinadora@uta.cl';
    correo.dispatchEvent(new Event('input'));
    clave.value = 'mala';
    clave.dispatchEvent(new Event('input'));
    el.querySelector('form')!.dispatchEvent(new Event('submit'));

    TestBed.inject(HttpTestingController).expectOne('/api/login').flush({}, { status: 422, statusText: 'Unprocessable' });
    fixture.detectChanges();
    await fixture.whenStable();

    expect(el.querySelector('[role=alert]')?.textContent).toContain('Correo o contraseña incorrectos');
  });
});
