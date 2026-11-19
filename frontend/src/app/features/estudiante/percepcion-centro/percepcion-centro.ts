import { HttpClient } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

interface Respuesta { clima_laboral: number | null; pago_viatico: number | null; pertinencia_tareas: number | null; observaciones: string }
interface Practica { id: number; tipo: string; centro: string; estado: string; percepcion: Respuesta | null }

@Component({
  selector: 'app-percepcion-centro',
  imports: [FormsModule, MatButtonModule, MatFormFieldModule, MatInputModule, MatSelectModule],
  templateUrl: './percepcion-centro.html',
  styleUrl: './percepcion-centro.scss',
})
export class PercepcionCentro {
  private readonly http = inject(HttpClient);
  protected readonly practicas = signal<Practica[]>([]);
  protected readonly seleccionada = signal<Practica | null>(null);
  protected readonly aviso = signal('');
  protected readonly guardando = signal(false);
  protected readonly cargando = signal(true);
  protected readonly errorCarga = signal(false);
  protected readonly escala = [1, 2, 3, 4, 5];
  protected respuesta: Respuesta = { clima_laboral: null, pago_viatico: null, pertinencia_tareas: null, observaciones: '' };

  constructor() {
    this.http.get<{ data: Practica[] }>('/api/estudiante/percepciones-centro').subscribe({
      next: ({ data }) => { this.practicas.set(data); this.cargando.set(false); },
      error: () => { this.errorCarga.set(true); this.cargando.set(false); this.aviso.set('No se pudieron cargar tus prácticas.'); },
    });
  }

  protected seleccionar(id: number): void {
    const practica = this.practicas().find((item) => item.id === id) ?? null;
    this.seleccionada.set(practica);
    this.respuesta = practica?.percepcion
      ? { ...practica.percepcion, observaciones: practica.percepcion.observaciones ?? '' }
      : { clima_laboral: null, pago_viatico: null, pertinencia_tareas: null, observaciones: '' };
    this.aviso.set('');
  }

  protected guardar(): void {
    const practica = this.seleccionada();
    if (!practica || !this.respuesta.clima_laboral || !this.respuesta.pago_viatico || !this.respuesta.pertinencia_tareas) return;
    this.guardando.set(true);
    this.http.put<{ data: Respuesta }>(`/api/estudiante/practicas/${practica.id}/percepcion-centro`, this.respuesta).subscribe({
      next: ({ data }) => {
        this.practicas.update((items) => items.map((item) => item.id === practica.id ? { ...item, percepcion: data } : item));
        this.aviso.set('Percepción guardada correctamente.');
        this.guardando.set(false);
      },
      error: () => { this.aviso.set('No se pudo guardar la percepción.'); this.guardando.set(false); },
    });
  }
}
