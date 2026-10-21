import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MetricasPracticas, PracticasApi } from './practicas-api';
import { NuevaPractica } from './nueva-practica';

@Component({
  selector: 'app-inicio-coordinacion',
  imports: [MatButtonModule, MatIconModule],
  templateUrl: './inicio.html',
  styleUrl: './inicio.scss',
})
export class InicioCoordinacion {
  private readonly api = inject(PracticasApi);
  private readonly dialogo = inject(MatDialog);
  private readonly mensajes = inject(MatSnackBar);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly datos = signal<MetricasPracticas | null>(null);
  protected readonly cargando = signal(false);
  protected readonly error = signal(false);
  protected readonly abierto = signal(false);

  constructor() { this.recargar(); }

  protected recargar(): void {
    this.cargando.set(true);
    this.error.set(false);
    this.api.metricas().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (datos) => { this.datos.set(datos); this.cargando.set(false); },
      error: () => { this.error.set(true); this.cargando.set(false); },
    });
  }

  protected nueva(): void {
    const periodo = this.datos()?.periodo;
    if (!periodo || this.abierto()) return;
    this.abierto.set(true);
    this.dialogo.open(NuevaPractica, { data: periodo, width: '620px', maxWidth: 'calc(100vw - 24px)', maxHeight: '94dvh' })
      .afterClosed().pipe(takeUntilDestroyed(this.destroyRef)).subscribe((creada: boolean | undefined) => {
        this.abierto.set(false);
        if (creada) {
          this.recargar();
          this.mensajes.open('Práctica excepcional registrada correctamente.', 'Cerrar', { duration: 6000 });
        }
      });
  }
}
