import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { InicioTemporal } from './inicio-temporal';

describe('InicioTemporal', () => {
  it('se crea', async () => {
    await TestBed.configureTestingModule({
      imports: [InicioTemporal],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();
    expect(TestBed.createComponent(InicioTemporal).componentInstance).toBeTruthy();
  });
});
