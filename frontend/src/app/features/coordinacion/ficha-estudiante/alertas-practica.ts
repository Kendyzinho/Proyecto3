import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

import { LUZ_ALERTA, TipoAlerta, fechaCorta, textoAlerta } from '../../../core/models/alerta';
import { NotificacionesAlertas } from '../../../shared/marco/notificaciones-alertas';
import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';

export interface AlertaFicha {
  id: number;
  tipo: TipoAlerta;
  fecha_referencia: string | null;
  fecha_atencion: string | null;
  atendida_por: string | null;
  nota: string | null;
  fecha_resolucion: string | null;
}

// HU-14: alertas de la práctica dentro de la ficha del estudiante (HU-17).
// Las pendientes se atienden aquí; las cerradas quedan como historial.
@Component({
  selector: 'app-alertas-practica',
  imports: [FormsModule, MatButtonModule, MatFormFieldModule, MatInputModule],
  template: `
    @if (pendientes().length || historial().length) {
      <section class="seccion">
        <h3>Alertas</h3>
        @if (error()) { <p class="aviso aviso--error" role="alert">{{ error() }}</p> }

        @if (pendientes().length) {
          <ul class="alertas">
            @for (alerta of pendientes(); track alerta.id) {
              <li>
                <span [class]="'luz luz--' + luces[alerta.tipo]" aria-hidden="true"></span>
                <div class="alertas__cuerpo">
                  <span>{{ texto(alerta) }}</span>
                  @if (atendiendo() === alerta.id) {
                    <form class="atender" (ngSubmit)="guardar(alerta)">
                      <mat-form-field subscriptSizing="dynamic">
                        <mat-label>Qué se hizo (opcional)</mat-label>
                        <textarea matInput name="nota" rows="2" maxlength="500" [(ngModel)]="nota"></textarea>
                      </mat-form-field>
                      <div class="atender__acciones">
                        <button matButton type="button" [disabled]="guardando()" (click)="atendiendo.set(null)">Cancelar</button>
                        <button matButton="filled" type="submit" [disabled]="guardando()">{{ guardando() ? 'Guardando…' : 'Guardar' }}</button>
                      </div>
                    </form>
                  }
                </div>
                @if (atendiendo() !== alerta.id) {
                  <button matButton type="button" [disabled]="atendiendo() !== null" (click)="empezar(alerta)">Marcar como atendida</button>
                }
              </li>
            }
          </ul>
        } @else {
          <p class="secundario">No hay alertas abiertas.</p>
        }

        @if (historial().length) {
          <h4>Historial</h4>
          <ul class="alertas alertas--historial">
            @for (alerta of historial(); track alerta.id) {
              <li>
                <span [class]="'luz luz--' + luces[alerta.tipo]" aria-hidden="true"></span>
                <div class="alertas__cuerpo">
                  <span>{{ texto(alerta) }}</span>
                  <small>{{ cierre(alerta) }}</small>
                </div>
              </li>
            }
          </ul>
        }
      </section>
    }
  `,
  styles: `
    .seccion { padding: 18px 0; border-bottom: 1px solid #e2e8f0; }
    h3 { margin: 0 0 8px; color: #253858; }
    h4 { margin: 16px 0 8px; color: #5b6b80; font-size: 13px; font-weight: 600; }
    .alertas { display: grid; gap: 12px; margin: 0; padding: 0; list-style: none; }
    .alertas li { display: flex; align-items: center; gap: 10px 14px; }
    .alertas--historial li, .alertas li:has(form) { align-items: flex-start; }
    .alertas__cuerpo { display: grid; flex: 1; gap: 2px; min-width: 0; }
    .alertas__cuerpo small { color: #5b6b80; font-size: 13px; overflow-wrap: anywhere; }
    .alertas--historial span { color: #5b6b80; }
    .luz { flex: none; width: 10px; height: 10px; border-radius: 50%; }
    .alertas--historial .luz, li:has(form) .luz { margin-top: 6px; }
    .luz--roja { background: #d93025; }
    .luz--naranja { background: #e8710a; }
    .luz--amarilla { background: #edb01c; }
    .atender { display: grid; gap: 8px; margin-top: 8px; }
    .atender__acciones { display: flex; justify-content: flex-end; gap: 8px; }
    .secundario { margin: 0; color: #5b6b80; font-size: 13px; }
    .aviso { padding: 12px 16px; border-radius: 8px; }
    .aviso--error { background: #fff1f2; color: #b91c1c; }
    @media (max-width: 600px) { .alertas li { flex-wrap: wrap; } }
  `,
})
export class AlertasPractica {
  readonly practicaId = input.required<number>();

  private readonly http = inject(HttpClient);
  private readonly notificaciones = inject(NotificacionesAlertas);

  protected readonly luces = LUZ_ALERTA;
  protected readonly datos = signal<{ pendientes: AlertaFicha[]; historial: AlertaFicha[] }>({ pendientes: [], historial: [] });
  protected readonly pendientes = computed(() => this.datos().pendientes);
  protected readonly historial = computed(() => this.datos().historial);
  protected readonly atendiendo = signal<number | null>(null);
  protected readonly guardando = signal(false);
  protected readonly error = signal('');
  protected nota = '';

  constructor() {
    effect(() => this.cargar(this.practicaId()));
  }

  protected texto(alerta: AlertaFicha): string {
    return textoAlerta(alerta.tipo, alerta.fecha_referencia);
  }

  protected cierre(alerta: AlertaFicha): string {
    if (alerta.fecha_atencion) {
      const base = `Atendida por ${alerta.atendida_por ?? 'Coordinación'} el ${fechaCorta(alerta.fecha_atencion)}`;
      return alerta.nota ? `${base}: ${alerta.nota}` : base;
    }
    return `Se resolvió el ${fechaCorta(alerta.fecha_resolucion)}`;
  }

  protected empezar(alerta: AlertaFicha): void {
    this.nota = '';
    this.error.set('');
    this.atendiendo.set(alerta.id);
  }

  protected guardar(alerta: AlertaFicha): void {
    this.guardando.set(true);
    this.http.post(`/api/coordinacion/alertas/${alerta.id}/atender`, { nota: this.nota.trim() || null }).subscribe({
      next: () => {
        this.guardando.set(false);
        this.atendiendo.set(null);
        this.cargar(this.practicaId());
        this.notificaciones.actualizar();
      },
      error: (e: HttpErrorResponse) => {
        this.guardando.set(false);
        this.error.set(mensajeDeError(e));
        this.cargar(this.practicaId());
      },
    });
  }

  private cargar(practicaId: number): void {
    this.http.get<{ data: { pendientes: AlertaFicha[]; historial: AlertaFicha[] } }>(`/api/coordinacion/practicas/${practicaId}/alertas`).subscribe({
      next: ({ data }) => this.datos.set(data),
      error: () => this.error.set('No se pudieron cargar las alertas.'),
    });
  }
}
