import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';

// Solicitudes por revisar que muestra el menú de la Coordinadora y del Jefe de Carrera
@Injectable({ providedIn: 'root' })
export class ContadorSolicitudes {
  private readonly http = inject(HttpClient);
  readonly pendientes = signal(0);

  actualizar(): void {
    this.http.get<{ solicitudes_por_revisar: number }>('/api/coordinacion/metricas').subscribe({
      next: ({ solicitudes_por_revisar }) => this.pendientes.set(solicitudes_por_revisar),
      error: () => this.pendientes.set(0),
    });
  }
}
