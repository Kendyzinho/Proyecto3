import { Injectable, inject, signal } from '@angular/core';

import { Alerta, AlertasApi } from '../../features/coordinacion/alertas/alertas-api';

//  alertas abiertas que la campana muestra a la Coordinadora
@Injectable({ providedIn: 'root' })
export class NotificacionesAlertas {
  private readonly api = inject(AlertasApi);
  readonly recientes = signal<Alerta[]>([]); // las 5 más graves (la API ya las ordena)
  readonly abiertas = signal(0);             // total para el contador rojo

  // Pide las alertas abiertas al backend
  actualizar(): void {
    this.api.listar('abiertas').subscribe({
      next: ({ data, meta }) => this.mostrar(data, meta.abiertas),
      error: () => this.mostrar([], 0),
    });
  }

  
  mostrar(abiertas: Alerta[] | null, total: number): void {
    if (abiertas) this.recientes.set(abiertas.slice(0, 5));
    this.abiertas.set(total);
  }
}