import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, computed, effect, inject, input, numberAttribute, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ActivatedRoute, Router } from '@angular/router';

import { ItemInstrumento, OpcionInstrumento, RespuestaItem } from '../../../core/models/instrumento';
import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { InformeLectura } from '../../coordinacion/informes/informe-lectura';
import { Informe, InformesSupervisorApi, SECCIONES_INFORME } from '../../coordinacion/informes/informes-api';

// HU-19: el supervisor(a) responde el informe confidencial de su estudiante (formulario oficial 2026)
@Component({
  selector: 'app-informe-supervisor',
  imports: [MatButtonModule, MatButtonToggleModule, MatFormFieldModule, MatInputModule, InformeLectura],
  templateUrl: './informe-supervisor.html',
  styleUrl: './informe-supervisor.scss',
})
export class InformeSupervisor {
  readonly practica = input.required({ transform: numberAttribute });

  private readonly api = inject(InformesSupervisorApi);
  private readonly aviso = inject(MatSnackBar);
  private readonly router = inject(Router);
  private readonly ruta = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly datos = signal<Informe | null>(null);
  protected readonly cargando = signal(true);
  protected readonly errorCarga = signal<string | null>(null);
  protected readonly opciones = signal<Record<number, number | undefined>>({});
  protected readonly textos = signal<Record<number, string | undefined>>({});
  protected readonly faltantes = signal<number[]>([]);
  protected readonly guardando = signal<'borrador' | 'envio' | null>(null);
  protected readonly error = signal<string | null>(null);

  protected readonly secciones = computed(() => {
    const items = this.datos()?.informe.items ?? [];
    return SECCIONES_INFORME
      .map((seccion) => ({ ...seccion, items: items.filter((item) => item.seccion === seccion.clave) }))
      .filter((seccion) => seccion.items.length > 0);
  });

  // La escala es la misma en todos los criterios: se explica una vez arriba
  protected readonly escala = computed(() =>
    (this.datos()?.informe.items.find((item) => item.tipo !== 'texto')?.opciones ?? []).filter((o) => o.valor !== null),
  );

  constructor() {
    effect(() => this.cargar(this.practica()));
  }

  protected volver(): void {
    this.router.navigate(['../..'], { relativeTo: this.ruta });
  }

  protected elegir(itemId: number, opcionId: number): void {
    this.opciones.update((o) => ({ ...o, [itemId]: opcionId }));
    this.faltantes.update((f) => f.filter((id) => id !== itemId));
  }

  protected escribir(itemId: number, texto: string): void {
    this.textos.update((t) => ({ ...t, [itemId]: texto }));
  }

  protected guardar(enviar: boolean): void {
    const datos = this.datos();
    if (!datos) return;
    if (enviar && !window.confirm(`¿Enviar el informe de ${datos.practica.estudiante}? Después no se puede modificar.`)) return;

    this.guardando.set(enviar ? 'envio' : 'borrador');
    this.error.set(null);
    this.api.guardar(this.practica(), this.respuestas(), enviar).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (informe) => {
        this.guardando.set(null);
        this.faltantes.set([]);
        this.datos.set(informe);
        this.aviso.open(enviar ? 'Informe enviado.' : 'Borrador guardado.', 'Cerrar', { duration: 5000 });
      },
      error: (e: HttpErrorResponse) => {
        this.guardando.set(null);
        this.faltantes.set(((e.error?.errors?.faltantes ?? []) as string[]).map(Number));
        this.error.set(e.status === 409 ? 'Este informe ya fue enviado y no se puede modificar.' : mensajeDeError(e));
      },
    });
  }

  protected esEscala(item: ItemInstrumento): boolean {
    return item.tipo !== 'texto';
  }

  // En los botones va el número; "No aplica" se escribe completo
  protected corta(opcion: OpcionInstrumento): string {
    return opcion.valor === null ? opcion.etiqueta : String(opcion.valor);
  }

  protected falta(item: ItemInstrumento): boolean {
    return this.faltantes().includes(item.id);
  }

  protected fecha(iso: string): string {
    const [anio, mes, dia] = iso.slice(0, 10).split('-');
    return `${dia}/${mes}/${anio}`;
  }

  private cargar(practica: number): void {
    this.cargando.set(true);
    this.errorCarga.set(null);
    this.api.ver(practica).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (datos) => {
        const opciones: Record<number, number | undefined> = {};
        const textos: Record<number, string | undefined> = {};
        for (const fila of datos.informe.respuestas) {
          if (fila.opcion_id != null) opciones[fila.item_id] = fila.opcion_id;
          if (fila.texto != null) textos[fila.item_id] = fila.texto;
        }
        this.opciones.set(opciones);
        this.textos.set(textos);
        this.datos.set(datos);
        this.cargando.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.cargando.set(false);
        this.errorCarga.set(e.status === 404 ? 'Este informe no existe o no te lo han solicitado.' : 'No se pudo cargar el informe.');
      },
    });
  }

  private respuestas(): RespuestaItem[] {
    const items = this.datos()?.informe.items ?? [];
    return items.flatMap((item): RespuestaItem[] => {
      if (!this.esEscala(item)) {
        const texto = (this.textos()[item.id] ?? '').trim();
        return texto ? [{ item_id: item.id, texto }] : [];
      }
      const opcion = this.opciones()[item.id];
      return opcion != null ? [{ item_id: item.id, opcion_id: opcion }] : [];
    });
  }
}
