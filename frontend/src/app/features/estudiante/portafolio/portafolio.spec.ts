import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { Portafolio } from './portafolio';

describe('Portafolio', () => {
  it('recupera, valida y actualiza el enlace de la práctica elegida', async () => {
    await TestBed.configureTestingModule({ imports: [Portafolio], providers: [provideHttpClient(), provideHttpClientTesting()] }).compileComponents();
    const fixture = TestBed.createComponent(Portafolio);
    const http = TestBed.inject(HttpTestingController);
    http.expectOne('/api/estudiante/portafolios').flush({ data: [{ id: 7, tipo: 'Profesional', centro: 'UTA', url: 'https://example.org/original' }] });
    const c = fixture.componentInstance;
    c.seleccionar(7);
    expect(c.url).toBe('https://example.org/original');
    c.url = 'javascript:alert(1)'; c.guardar();
    http.expectNone('/api/estudiante/practicas/7/portafolio');
    expect(c.error()).toContain('URL válida');
    c.url = 'https://example.org/nuevo'; c.guardar();
    const request = http.expectOne('/api/estudiante/practicas/7/portafolio');
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({ url: c.url });
    request.flush({ data: { url: c.url } });
    fixture.detectChanges(); await fixture.whenStable();
    expect(fixture.nativeElement.querySelector('a')?.getAttribute('href')).toBe(c.url);
    expect(fixture.nativeElement.querySelector('input[type=file]')).toBeNull();
    http.verify();
  });
});
