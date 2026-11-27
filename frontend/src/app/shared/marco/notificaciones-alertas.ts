import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';

import { TipoAlerta } from '../../core/models/alerta';
import { Auth } from '../../core/services/auth';

// Una línea de la campana tal como la entrega la API
export interface Notificacion {
  id: number;
  tipo: TipoAlerta;
  fecha_referencia: string | null;
  practica_id: number;
  estudiante: string;
  otras: number;     // Coordinación: cuántas alertas más tiene esa práctica
  enlace: string | null; // Supervisor(a): página para confirmar la semana
}

// HU-14: lo que muestra la campana; cada rol recibe lo suyo desde /api/notificaciones
@Injectable({ providedIn: 'root' })
export class NotificacionesAlertas {
  private readonly http = inject(HttpClient);
  readonly lineas = signal<Notificacion[]>([]);
  readonly total = signal(0);
  // Cambia en cada cierre de sesión: una respuesta pedida con la sesión anterior se descarta
  private sesion = 0;

  constructor() {
    // Lo de la sesión anterior no se le muestra a la siguiente persona que entra
    inject(Auth).sesionFinalizada$.subscribe(() => this.limpiar());
  }

  actualizar(): void {
    const sesion = this.sesion;
    this.http.get<{ data: Notificacion[]; meta: { total: number } }>('/api/notificaciones').subscribe({
      next: ({ data, meta }) => {
        if (sesion !== this.sesion) return;
        this.lineas.set(data);
        this.total.set(meta.total);
      },
      error: () => {
        if (sesion === this.sesion) this.limpiar();
      },
    });
  }

  limpiar(): void {
    this.sesion++;
    this.lineas.set([]);
    this.total.set(0);
  }
}
