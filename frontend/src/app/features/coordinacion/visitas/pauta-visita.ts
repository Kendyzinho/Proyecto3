import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, effect, inject, input, numberAttribute, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSnackBar } from '@angular/material/snack-bar';
import { ActivatedRoute, Router } from '@angular/router';

import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { FormularioInstrumento, ItemInstrumento, PracticaPauta, RespuestaItem, Visita, VisitasApi } from './visitas-api';

// Lo que la Coordinadora va marcando para un estudiante
interface Pauta {
  practica: PracticaPauta;
  formulario: FormularioInstrumento;
  enviadaEn: string | null;
  opciones: Record<number, number | null>; // criterio → opción elegida
  textos: Record<number, string | undefined>; // criterio de texto → lo escrito
  faltantes: number[];
  guardando: 'borrador' | 'envio' | null;
  error: string | null;
}

const MODALIDADES: Record<string, string> = { terreno: 'Terreno', virtual: 'Virtual' };

// HU-16: pauta de la visita, una por cada estudiante revisado
@Component({
  selector: 'app-pauta-visita',
  imports: [MatButtonModule, MatButtonToggleModule, MatFormFieldModule, MatInputModule],
  templateUrl: './pauta-visita.html',
  styleUrl: './pauta-visita.scss',
})
export class PautaVisita {
  readonly id = input.required({ transform: numberAttribute });

  private readonly api = inject(VisitasApi);
  private readonly aviso = inject(MatSnackBar);
  private readonly router = inject(Router);
  private readonly ruta = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  protected readonly visita = signal<Visita | null>(null);
  protected readonly pautas = signal<Pauta[]>([]);
  protected readonly cargando = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    effect(() => this.cargar(this.id()));
  }

  protected cargar(id = this.id()): void {
    this.cargando.set(true);
    this.error.set(null);
    this.api.pautas(id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: ({ visita, formulario, practicas }) => {
        this.visita.set(visita);
        this.pautas.set(
          practicas
            .filter((practica) => practica.respuesta || formulario)
            .map((practica) => this.armar(practica, formulario!)),
        );
        this.cargando.set(false);
      },
      error: (e: HttpErrorResponse) => {
        this.cargando.set(false);
        this.error.set(e.status === 404 ? 'La visita no existe.' : 'No se pudo cargar la pauta.');
      },
    });
  }

  protected volver(): void {
    this.router.navigate(['../..'], { relativeTo: this.ruta });
  }

  protected elegir(practicaId: number, itemId: number, opcionId: number): void {
    this.cambiar(practicaId, (p) => ({
      opciones: { ...p.opciones, [itemId]: opcionId },
      faltantes: p.faltantes.filter((id) => id !== itemId),
    }));
  }

  protected escribir(practicaId: number, itemId: number, texto: string): void {
    this.cambiar(practicaId, (p) => ({
      textos: { ...p.textos, [itemId]: texto },
      faltantes: texto.trim() ? p.faltantes.filter((id) => id !== itemId) : p.faltantes,
    }));
  }

  protected guardar(pauta: Pauta, enviar: boolean): void {
    if (enviar && !window.confirm(`¿Enviar la pauta de ${pauta.practica.estudiante}? Después no se puede modificar.`)) return;

    const id = pauta.practica.id;
    this.cambiar(id, () => ({ guardando: enviar ? 'envio' : 'borrador', error: null }));
    this.api.guardarPauta(this.id(), id, { respuestas: this.respuestas(pauta), enviar })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (respuesta) => {
          // La respuesta guardada pasa a ser la de la práctica: así aparece "Borrador" sin recargar
          this.cambiar(id, (p) => ({ guardando: null, enviadaEn: respuesta.enviada_en, faltantes: [], practica: { ...p.practica, respuesta } }));
          this.aviso.open(enviar ? 'Pauta enviada.' : 'Borrador guardado.', 'Cerrar', { duration: 5000 });
        },
        error: (e: HttpErrorResponse) => {
          const faltantes = ((e.error?.errors?.faltantes ?? []) as string[]).map(Number);
          const mensaje = e.status === 409 ? 'Esta pauta ya fue enviada y no se puede modificar.' : mensajeDeError(e);
          this.cambiar(id, () => ({ guardando: null, faltantes, error: mensaje }));
        },
      });
  }

  protected opciones(item: ItemInstrumento): boolean {
    return item.tipo !== 'texto';
  }

  protected elegida(pauta: Pauta, item: ItemInstrumento): string {
    const opcion = item.opciones.find((o) => o.id === pauta.opciones[item.id]);
    return opcion?.etiqueta ?? 'Sin respuesta';
  }

  protected falta(pauta: Pauta, item: ItemInstrumento): boolean {
    return pauta.faltantes.includes(item.id);
  }

  protected modalidad(visita: Visita): string {
    return MODALIDADES[visita.modalidad] ?? visita.modalidad;
  }

  protected fecha(fechaHora: string): string {
    const [anio, mes, dia] = fechaHora.slice(0, 10).split('-');
    return `${dia}/${mes}/${anio}`;
  }

  protected hora(visita: Visita): string {
    return visita.fecha_hora.slice(11, 16);
  }

  private armar(practica: PracticaPauta, formulario: FormularioInstrumento): Pauta {
    const respuesta = practica.respuesta;
    const opciones: Record<number, number | null> = {};
    const textos: Record<number, string | undefined> = {};
    for (const fila of respuesta?.respuestas ?? []) {
      if (fila.opcion_id != null) opciones[fila.item_id] = fila.opcion_id;
      if (fila.texto != null) textos[fila.item_id] = fila.texto;
    }

    return {
      practica,
      // Una pauta ya empezada usa su propia plantilla
      formulario: respuesta ?? formulario,
      enviadaEn: respuesta?.enviada_en ?? null,
      opciones,
      textos,
      faltantes: [],
      guardando: null,
      error: null,
    };
  }

  private respuestas(pauta: Pauta): RespuestaItem[] {
    return pauta.formulario.items.flatMap((item): RespuestaItem[] => {
      if (!this.opciones(item)) {
        const texto = (pauta.textos[item.id] ?? '').trim();
        return texto ? [{ item_id: item.id, texto }] : [];
      }
      const opcion = pauta.opciones[item.id];
      return opcion != null ? [{ item_id: item.id, opcion_id: opcion }] : [];
    });
  }

  private cambiar(practicaId: number, cambios: (pauta: Pauta) => Partial<Pauta>): void {
    this.pautas.update((pautas) => pautas.map((p) => (p.practica.id === practicaId ? { ...p, ...cambios(p) } : p)));
  }
}
