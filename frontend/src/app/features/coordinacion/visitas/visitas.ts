import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import { forkJoin } from 'rxjs';

import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { AgendarVisita, DatosDialogoVisita } from './agendar-visita';
import { CentroSinVisita, EstadoVisita, ModalidadVisita, Visita, VisitasApi } from './visitas-api';

const ESTADOS: Record<EstadoVisita, { texto: string; clase: string }> = {
  agendada: { texto: 'Agendada', clase: 'estado--info' },
  realizada: { texto: 'Realizada', clase: 'estado--exito' },
  cancelada: { texto: 'Cancelada', clase: 'estado--neutro' },
};

const MODALIDADES: Record<ModalidadVisita, string> = { terreno: 'Terreno', virtual: 'Virtual' };

// HU-15: agenda de visitas técnicas y centros sin visita este semestre
@Component({
  selector: 'app-visitas',
  imports: [MatButtonModule, MatIconModule, MatTableModule],
  templateUrl: './visitas.html',
  styleUrl: './visitas.scss',
})
export class Visitas {
  private readonly api = inject(VisitasApi);
  private readonly dialogo = inject(MatDialog);
  private readonly aviso = inject(MatSnackBar);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly columnas = ['fecha', 'hora', 'modalidad', 'centro', 'estudiantes', 'estado', 'acciones'];
  protected readonly visitas = signal<Visita[]>([]);
  protected readonly sinVisita = signal<CentroSinVisita[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal(false);
  protected readonly cancelando = signal<number | null>(null);

  constructor() {
    this.cargar();
  }

  protected cargar(): void {
    this.cargando.set(true);
    this.error.set(false);
    forkJoin({ visitas: this.api.listar(), sinVisita: this.api.centrosSinVisita() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: ({ visitas, sinVisita }) => {
          this.visitas.set(visitas);
          this.sinVisita.set(sinVisita);
          this.cargando.set(false);
        },
        error: () => {
          this.cargando.set(false);
          this.error.set(true);
        },
      });
  }

  protected agendar(centro?: CentroSinVisita): void {
    this.abrir(centro ? { centroId: centro.id, practicas: centro.practicas.map((p) => p.id) } : {}, 'Visita agendada.');
  }

  protected editar(visita: Visita): void {
    this.abrir({ visita }, 'Visita actualizada.');
  }

  protected cancelar(visita: Visita): void {
    if (!window.confirm(`¿Cancelar la visita a ${visita.centro?.nombre ?? 'este centro'} del ${this.fecha(visita)}?`)) return;
    this.cancelando.set(visita.id);
    this.api.cancelar(visita.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.cancelando.set(null);
        this.aviso.open('Visita cancelada.', 'Cerrar', { duration: 5000 });
        this.cargar();
      },
      error: (e: HttpErrorResponse) => {
        this.cancelando.set(null);
        this.aviso.open(mensajeDeError(e), 'Cerrar', { duration: 6000 });
      },
    });
  }

  protected estado(visita: Visita): { texto: string; clase: string } {
    return ESTADOS[visita.estado];
  }

  protected modalidad(visita: Visita): string {
    return MODALIDADES[visita.modalidad];
  }

  protected fecha(visita: Visita): string {
    const [anio, mes, dia] = visita.fecha_hora.slice(0, 10).split('-');
    return `${dia}/${mes}/${anio}`;
  }

  protected hora(visita: Visita): string {
    return visita.fecha_hora.slice(11, 16);
  }

  protected estudiantes(practicas: { estudiante: string }[]): string {
    return practicas.map((p) => p.estudiante).join(', ');
  }

  private abrir(datos: DatosDialogoVisita, mensaje: string): void {
    this.dialogo.open(AgendarVisita, { data: datos, width: '560px', maxWidth: 'calc(100vw - 32px)', autoFocus: 'first-tabbable' })
      .afterClosed().pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((visita?: Visita) => {
        if (!visita) return;
        this.aviso.open(mensaje, 'Cerrar', { duration: 5000 });
        this.cargar();
      });
  }
}
