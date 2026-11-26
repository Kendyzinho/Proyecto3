import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import { ActivatedRoute, Router } from '@angular/router';

import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { ESTADOS_INFORME, FilaInforme, InformesApi } from './informes-api';

// HU-19: la Coordinadora (o el Jefe) pide el informe confidencial al supervisor(a) y lo lee cuando lo envía
@Component({
  selector: 'app-informes',
  imports: [MatButtonModule, MatTableModule],
  templateUrl: './informes.html',
  styleUrl: './informes.scss',
})
export class Informes {
  private readonly api = inject(InformesApi);
  private readonly aviso = inject(MatSnackBar);
  private readonly router = inject(Router);
  private readonly ruta = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly columnas = ['estudiante', 'tipo', 'centro', 'supervisor', 'estado', 'acciones'];
  protected readonly filas = signal<FilaInforme[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal(false);
  protected readonly solicitando = signal<number | null>(null);

  constructor() {
    this.cargar();
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(false);
    this.api.listar().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (filas) => {
        this.filas.set(filas);
        this.cargando.set(false);
      },
      error: () => {
        this.cargando.set(false);
        this.error.set(true);
      },
    });
  }

  protected solicitar(fila: FilaInforme): void {
    const pregunta = fila.estado === 'sin_solicitar'
      ? `¿Pedirle a ${fila.supervisor} el informe confidencial de ${fila.estudiante}?`
      : `¿Reenviarle a ${fila.supervisor} el aviso del informe de ${fila.estudiante}?`;
    if (!window.confirm(pregunta)) return;

    this.solicitando.set(fila.id);
    this.api.solicitar(fila.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: ({ data, message }) => {
        this.solicitando.set(null);
        this.filas.update((filas) => filas.map((f) => (f.id === data.id ? data : f)));
        this.aviso.open(message, 'Cerrar', { duration: 6000 });
      },
      error: (e: HttpErrorResponse) => {
        this.solicitando.set(null);
        this.aviso.open(mensajeDeError(e), 'Cerrar', { duration: 6000 });
      },
    });
  }

  protected ver(fila: FilaInforme): void {
    this.router.navigate([fila.id], { relativeTo: this.ruta });
  }

  protected estado(fila: FilaInforme): { texto: string; clase: string } {
    return ESTADOS_INFORME[fila.estado];
  }
}
