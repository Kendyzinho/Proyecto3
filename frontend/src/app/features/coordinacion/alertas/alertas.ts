import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar } from '@angular/material/snack-bar';

import { NotificacionesAlertas } from '../../../shared/marco/notificaciones-alertas';
import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { Alerta, AlertasApi, EstadoAlertas, TIPOS_ALERTA, fechaLegible } from './alertas-api';

// HU-14: panel de alertas tempranas (luces rojas y amarillas)
@Component({
  selector: 'app-alertas',
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './alertas.html',
  styleUrl: './alertas.scss',
})
export class Alertas {
  private readonly api = inject(AlertasApi);
  private readonly notificaciones = inject(NotificacionesAlertas);
  private readonly aviso = inject(MatSnackBar);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly tipos = TIPOS_ALERTA;
  protected readonly estado = signal<EstadoAlertas>('abiertas');
  protected readonly alertas = signal<Alerta[]>([]);
  protected readonly totales = signal({ abiertas: 0, atendidas: 0 });
  protected readonly cargando = signal(true);
  protected readonly error = signal(false);
  protected readonly atendiendo = signal<number | null>(null);

  protected readonly fechaLegible = fechaLegible;

  constructor() {
    this.cargar();
  }

  protected cambiar(estado: EstadoAlertas): void {
    if (estado === this.estado()) return;
    this.estado.set(estado);
    this.cargar();
  }

  protected detalle(alerta: Alerta): string {
    return TIPOS_ALERTA[alerta.tipo].detalle(fechaLegible(alerta.fecha_referencia));
  }

  protected atender(alerta: Alerta): void {
    this.atendiendo.set(alerta.id);
    this.api.atender(alerta.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.atendiendo.set(null);
        this.aviso.open(`Alerta de ${alerta.practica.estudiante} marcada como atendida.`, 'Cerrar', { duration: 5000 });
        this.cargar();
      },
      error: (e: HttpErrorResponse) => {
        this.atendiendo.set(null);
        this.aviso.open(mensajeDeError(e), 'Cerrar', { duration: 5000 });
        this.cargar();
      },
    });
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(false);
    this.api.listar(this.estado()).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: ({ data, meta }) => {
        this.alertas.set(data);
        this.totales.set(meta);
        this.cargando.set(false);
        // La campana de la barra superior se actualiza con la misma respuesta
        this.notificaciones.mostrar(this.estado() === 'abiertas' ? data : null, meta.abiertas);
      },
      error: () => {
        this.error.set(true);
        this.cargando.set(false);
      },
    });
  }
}