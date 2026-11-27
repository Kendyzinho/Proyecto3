import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, computed, effect, inject, input, numberAttribute, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSnackBar } from '@angular/material/snack-bar';
import { Router } from '@angular/router';

import { ConfirmacionSemanalApi, DiaRegistrado, SemanaPorConfirmar, Validacion, fechaCorta } from './confirmacion-semanal-api';

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// HU-26: el supervisor confirma o rechaza cada dia pendiente de la semana
@Component({
  selector: 'app-confirmacion-semanal',
  imports: [DatePipe, MatButtonModule, MatButtonToggleModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './confirmacion-semanal.html',
  styleUrl: './confirmacion-semanal.scss',
})
export class ConfirmacionSemanal {
  readonly practica = input.required({ transform: numberAttribute });
  readonly semana = input.required<string>();

  private readonly api = inject(ConfirmacionSemanalApi);
  private readonly aviso = inject(MatSnackBar);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly datos = signal<SemanaPorConfirmar | null>(null);
  protected readonly cargando = signal(true);
  protected readonly errorCarga = signal<string | null>(null);
  protected readonly decisiones = signal<Record<number, Validacion>>({});
  protected readonly observacion = signal('');
  protected readonly enviando = signal(false);
  protected readonly error = signal<string | null>(null);

  // Días que faltan por decidir: los nuevos y los corregidos tras un rechazo
  protected readonly pendientes = computed(() => (this.datos()?.dias ?? []).filter((d) => d.validacion === null));
  protected readonly completa = computed(
    () => this.pendientes().length > 0 && this.pendientes().every((d) => !!this.decisiones()[d.id]),
  );
  protected readonly fecha = fechaCorta;

  constructor() {
    effect(() => this.cargar(this.practica(), this.semana()));
  }

  protected volver(): void {
    this.router.navigate(['/supervisor/asistencia']);
  }

  protected decidir(id: number, validacion: Validacion): void {
    this.decisiones.update((actuales) => ({ ...actuales, [id]: validacion }));
  }

  protected enviar(): void {
    if (!this.completa()) return;
    this.error.set(null);
    this.enviando.set(true);

    this.api.confirmar(this.practica(), this.semana(), {
      dias: this.pendientes().map((d) => ({ id: d.id, validacion: this.decisiones()[d.id] })),
      observacion: this.observacion().trim() || null,
    }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (datos) => {
        this.enviando.set(false);
        this.mostrar(datos);
        this.aviso.open('Confirmación enviada.', 'Cerrar', { duration: 5000 });
      },
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        // 409: ya no quedan días pendientes (otra pestaña); se muestra lo que quedó guardado
        if (e.status === 409) {
          this.cargar(this.practica(), this.semana());
          return;
        }
        this.error.set(
          e.status === 422 ? 'Confirma o rechaza cada día pendiente antes de enviar.'
            : e.status === 0 ? 'No se pudo conectar con el servidor.'
              : 'Ocurrió un error inesperado. Inténtalo de nuevo.',
        );
      },
    });
  }

  protected dia(fecha: string): string {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    return `${DIAS[new Date(anio, mes - 1, dia).getDay()]} ${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;
  }

  protected resultado(d: DiaRegistrado): string {
    return d.validacion === 'confirmado' ? 'Confirmado' : 'Rechazado';
  }

  private cargar(practica: number, semana: string): void {
    this.cargando.set(true);
    this.errorCarga.set(null);
    this.api.ver(practica, semana).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (datos) => this.mostrar(datos),
      error: (e: HttpErrorResponse) => {
        this.cargando.set(false);
        this.errorCarga.set(
          e.status === 404 ? 'Esta semana no existe o no corresponde a una práctica que supervises.'
            : 'No se pudo cargar la semana. Inténtalo de nuevo.',
        );
      },
    });
  }

  private mostrar(datos: SemanaPorConfirmar): void {
    this.datos.set(datos);
    this.decisiones.set({});
    this.observacion.set('');
    this.cargando.set(false);
  }
}