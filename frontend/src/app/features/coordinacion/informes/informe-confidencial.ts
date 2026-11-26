import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, effect, inject, input, numberAttribute, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { ActivatedRoute, Router } from '@angular/router';

import { InformeLectura } from './informe-lectura';
import { Informe, InformesApi } from './informes-api';

// HU-19: la Coordinación lee el informe confidencial que envió el supervisor(a)
@Component({
  selector: 'app-informe-confidencial',
  imports: [MatButtonModule, InformeLectura],
  template: `
    <p class="migas">
      <button type="button" (click)="volver()">Informes confidenciales</button>
      <span aria-hidden="true">/</span><span>Informe</span>
    </p>
    <header class="encabezado-pagina">
      <h1>Informe confidencial</h1>
    </header>

    @if (cargando()) {
      <section class="tarjeta"><p class="vacio">Cargando informe…</p></section>
    } @else if (error(); as mensaje) {
      <section class="tarjeta">
        <div class="vacio" role="alert">
          <p>{{ mensaje }}</p>
          <button matButton type="button" (click)="volver()">Volver a los informes</button>
        </div>
      </section>
    } @else if (datos(); as d) {
      <section class="tarjeta">
        <dl class="datos">
          <div><dt>Estudiante</dt><dd>{{ d.practica.estudiante }}</dd></div>
          <div><dt>Práctica</dt><dd>{{ d.practica.tipo_practica }}</dd></div>
          <div><dt>Centro</dt><dd>{{ d.practica.centro }}</dd></div>
          <div><dt>Supervisor(a)</dt><dd>{{ d.practica.supervisor }}</dd></div>
          @if (d.informe.enviada_en) {
            <div><dt>Enviado</dt><dd>{{ fecha(d.informe.enviada_en) }}</dd></div>
          }
        </dl>
      </section>
      <app-informe-lectura [informe]="d.informe" />
    }
  `,
  styles: `
    .vacio {
      margin: 0;
      padding: 24px;
      color: #5b6b80;
    }

    .datos {
      display: flex;
      flex-wrap: wrap;
      gap: 16px 40px;
      margin: 0;
      padding: 20px 24px;

      dt {
        color: #5b6b80;
        font-size: 12px;
      }

      dd {
        margin: 2px 0 0;
        color: #1e293b;
        font-size: 14px;
      }
    }
  `,
})
export class InformeConfidencial {
  readonly practica = input.required({ transform: numberAttribute });

  private readonly api = inject(InformesApi);
  private readonly router = inject(Router);
  private readonly ruta = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly datos = signal<Informe | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    effect(() => this.cargar(this.practica()));
  }

  protected volver(): void {
    this.router.navigate(['..'], { relativeTo: this.ruta });
  }

  protected fecha(iso: string): string {
    const [anio, mes, dia] = iso.slice(0, 10).split('-');
    return `${dia}/${mes}/${anio}`;
  }

  private cargar(practica: number): void {
    this.cargando.set(true);
    this.error.set(null);
    this.api.ver(practica).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (datos) => {
        this.datos.set(datos);
        this.cargando.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.cargando.set(false);
        this.error.set(e.status === 404 ? 'El informe todavía no ha sido enviado.' : 'No se pudo cargar el informe.');
      },
    });
  }
}
