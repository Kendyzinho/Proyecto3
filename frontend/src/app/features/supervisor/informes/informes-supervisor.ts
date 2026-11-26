import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';

import { ESTADOS_INFORME, FilaInforme, InformesSupervisorApi } from '../../coordinacion/informes/informes-api';

// HU-19: inicio del supervisor(a): los informes confidenciales que le pidieron de sus estudiantes
@Component({
  selector: 'app-informes-supervisor',
  imports: [MatButtonModule, MatTableModule, RouterLink],
  template: `
    <header class="encabezado-pagina">
      <h1>Informes confidenciales</h1>
    </header>

    <section class="tarjeta" aria-labelledby="pedidos-titulo">
      <div class="tarjeta__cabecera">
        <h2 id="pedidos-titulo">Informes solicitados</h2>
      </div>
      @if (cargando()) {
        <p class="vacio">Cargando informes…</p>
      } @else if (error()) {
        <div class="vacio" role="alert">
          <p>No se pudieron cargar los informes.</p>
          <button matButton type="button" (click)="cargar()">Reintentar</button>
        </div>
      } @else if (filas().length === 0) {
        <p class="vacio">No tienes informes por responder.</p>
      } @else {
        <div class="tabla-contenedor">
          <table mat-table [dataSource]="filas()" class="tabla">
            <ng-container matColumnDef="estudiante">
              <th mat-header-cell *matHeaderCellDef>Estudiante</th>
              <td mat-cell *matCellDef="let fila">{{ fila.estudiante }}</td>
            </ng-container>
            <ng-container matColumnDef="tipo">
              <th mat-header-cell *matHeaderCellDef>Práctica</th>
              <td mat-cell *matCellDef="let fila">{{ fila.tipo_practica }}</td>
            </ng-container>
            <ng-container matColumnDef="centro">
              <th mat-header-cell *matHeaderCellDef>Centro</th>
              <td mat-cell *matCellDef="let fila">{{ fila.centro }}</td>
            </ng-container>
            <ng-container matColumnDef="estado">
              <th mat-header-cell *matHeaderCellDef>Estado</th>
              <td mat-cell *matCellDef="let fila">
                <span [class]="'estado ' + estado(fila).clase">{{ estado(fila).texto }}</span>
              </td>
            </ng-container>
            <ng-container matColumnDef="acciones">
              <th mat-header-cell *matHeaderCellDef><span class="cdk-visually-hidden">Acciones</span></th>
              <td mat-cell *matCellDef="let fila" class="acciones">
                <a matButton [routerLink]="['informes', fila.id]">{{ accion(fila) }}</a>
              </td>
            </ng-container>
            <tr mat-header-row *matHeaderRowDef="columnas"></tr>
            <tr mat-row *matRowDef="let fila; columns: columnas"></tr>
          </table>
        </div>
      }
    </section>
  `,
  styles: `
    .vacio {
      margin: 0;
      padding: 24px;
      color: #5b6b80;
    }

    .tabla-contenedor {
      overflow-x: auto;
    }

    .acciones {
      white-space: nowrap;
      text-align: right;
    }
  `,
})
export class InformesSupervisor {
  private readonly api = inject(InformesSupervisorApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly columnas = ['estudiante', 'tipo', 'centro', 'estado', 'acciones'];
  protected readonly filas = signal<FilaInforme[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal(false);

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

  protected estado(fila: FilaInforme): { texto: string; clase: string } {
    return ESTADOS_INFORME[fila.estado];
  }

  protected accion(fila: FilaInforme): string {
    if (fila.estado === 'enviado') return 'Ver informe';
    return fila.estado === 'borrador' ? 'Continuar' : 'Responder';
  }
}
