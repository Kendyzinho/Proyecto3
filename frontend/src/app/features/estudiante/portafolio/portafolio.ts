import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { PortafolioApi, PracticaPortafolio } from './portafolio-api';

@Component({
  selector: 'app-portafolio',
  imports: [FormsModule, MatButtonModule, MatFormFieldModule, MatInputModule, MatSelectModule],
  templateUrl: './portafolio.html',
  styles: [':host { display: block; } mat-form-field { display: block; width: 100%; } .acciones { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; }'],
})
export class Portafolio {
  private readonly api = inject(PortafolioApi);
  readonly practicas = signal<PracticaPortafolio[]>([]);
  readonly cargando = signal(true);
  readonly error = signal('');
  readonly mensaje = signal('');
  readonly guardando = signal(false);
  seleccion: number | null = null;
  url = '';
  readonly guardada = signal<string | null>(null);
  constructor() {
    this.api.listar().subscribe({
      next: ({ data }) => { this.practicas.set(data); this.cargando.set(false); },
      error: () => { this.error.set('No se pudieron cargar tus prácticas. Recarga la página para intentarlo nuevamente.'); this.cargando.set(false); },
    });
  }
  seleccionar(id: number) {
    this.seleccion = id;
    this.url = this.practicas().find(p => p.id === id)?.url ?? '';
    this.guardada.set(this.valida(this.url) ? this.url : null);
    this.error.set(''); this.mensaje.set('');
  }
  valida(url: string): boolean {
    try { const u = new URL(url); return ['http:', 'https:'].includes(u.protocol) && !!u.hostname && url.length <= 255; } catch { return false; }
  }
  guardar() {
    if (this.guardando() || this.seleccion === null) return;
    const url = this.url.trim();
    if (!this.valida(url)) { this.error.set('Ingresa una URL válida que comience con https:// o http:// (máximo 255 caracteres).'); return; }
    const id = this.seleccion;
    this.guardando.set(true); this.error.set(''); this.mensaje.set('');
    this.api.guardar(id, url).subscribe({
      next: ({ data }) => {
        this.practicas.update(ps => ps.map(p => p.id === id ? { ...p, url: data.url } : p));
        this.guardada.set(data.url); this.url = data.url;
        this.guardando.set(false); this.mensaje.set('Enlace del portafolio guardado correctamente.');
      },
      error: (e) => { this.guardando.set(false); this.error.set(e.status === 422 ? 'Revisa el enlace: debe ser una URL HTTP/HTTPS de hasta 255 caracteres.' : 'No se pudo guardar el enlace. Inténtalo nuevamente.'); },
    });
  }
}
