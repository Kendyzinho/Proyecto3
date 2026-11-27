import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';

import { ConfirmacionSemanalApi, FilaSemana, fechaCorta } from './confirmacion-semanal-api';

// HU-26: semanas de asistencia de sus estudiantes , primero las que tienen días por confirmar
@Component({
  selector: 'app-asistencia-supervisor',
  imports: [MatButtonModule, MatTableModule, RouterLink],
  template: `
    <header class="encabezado-pagina">
      <h1>Asistencia semanal</h1>
    </header>

    <section class="tarjeta" aria-labelledby="semanas-titulo">
      <div class="tarjeta__cabecera">
        <h2 id="semanas-titulo">Semanas registradas</h2>
      </div>
      @if (cargando()) {
        <p class="vacio">Cargando semanas…</p>
      } @else if (error()) {
        <div class="vacio" role="alert">
          <p>No se pudieron cargar las semanas.</p>
          <button matButton type="button" (click)="cargar()">Reintentar</button>
        </div>
      } @else if (filas().length === 0) {
        <p class="vacio">No tienes semanas por confirmar.</p>
      } @else {
        <div class="tabla-contenedor">
          <table mat-table [dataSource]="filas()" class="tabla">
            <ng-container matColumnDef="estudiante">
              <th mat-header-cell *matHeaderCellDef>Estudiante</th>
              <td mat-cell *matCellDef="let fila">{{ fila.estudiante }}</td>
            </ng-container>
            <ng-container matColumnDef="centro">
              <th mat-header-cell *matHeaderCellDef>Centro</th>
              <td mat-cell *matCellDef="let fila">{{ fila.centro }}</td>
            </ng-container>
            <ng-container matColumnDef="semana">
              <th mat-header-cell *matHeaderCellDef>Semana</th>
              <td mat-cell *matCellDef="let fila">{{ fecha(fila.semana.desde) }} al {{ fecha(fila.semana.hasta) }}</td>
            </ng-container>
            <ng-container matColumnDef="estado">
              <th mat-header-cell *matHeaderCellDef>Estado</th>
              <td mat-cell *matCellDef="let fila">
                @if (fila.pendientes > 0) {
                  <span class="estado estado--advertencia">{{ fila.pendientes }} {{ fila.pendientes === 1 ? 'día' : 'días' }} por confirmar</span>
                } @else {
                  <span class="estado estado--exito">Confirmada</span>
                }
              </td>
            </ng-container>
            <ng-container matColumnDef="acciones">
              <th mat-header-cell *matHeaderCellDef><span class="cdk-visually-hidden">Acciones</span></th>
              <td mat-cell *matCellDef="let fila" class="acciones">
                <a matButton [routerLink]="[fila.practica_id, fila.semana.desde]">{{ fila.pendientes > 0 ? 'Revisar' : 'Ver' }}</a>
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
export class AsistenciaSupervisor {
  private readonly api = inject(ConfirmacionSemanalApi);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly columnas = ['estudiante', 'centro', 'semana', 'estado', 'acciones'];
  protected readonly filas = signal<FilaSemana[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal(false);
  protected readonly fecha = fechaCorta;

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
}