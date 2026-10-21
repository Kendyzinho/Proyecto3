import { KeyValuePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTabsModule } from '@angular/material/tabs';

import { iniciales } from '../../../shared/marco/marco';
import { descargar } from '../nomina/nomina-api';
import { AcuerdoCentro } from './acuerdo-centro';
import { DetallePractica, DocumentosApi, PeriodoActual, PracticaPorFormalizar, TipoDocumento, claseEstado, textoEstado } from './documentos-api';

const NOMBRES_PDF: Record<TipoDocumento, string> = {
  carta: 'Carta de presentación.pdf',
  seguro: 'Guía del seguro escolar.pdf',
};

// HU-10: carta de presentación, guía del seguro escolar y acuerdo de colaboración (pantalla 08)
@Component({
  selector: 'app-documentos',
  imports: [KeyValuePipe, MatButtonModule, MatIconModule, MatTabsModule, AcuerdoCentro],
  templateUrl: './documentos.html',
  styleUrl: './documentos.scss',
})
export class Documentos {
  private readonly api = inject(DocumentosApi);
  private readonly aviso = inject(MatSnackBar);

  protected readonly periodo = signal<PeriodoActual | null>(null);
  protected readonly practicas = signal<PracticaPorFormalizar[]>([]);
  protected readonly detalle = signal<DetallePractica | null>(null);
  protected readonly pestana = signal(0);
  protected readonly ocupado = signal(false);

  // Pestaña 0 = carta, 1 = seguro; la 2 (acuerdo) no tiene PDF propio
  protected readonly tipoActual = computed<TipoDocumento | null>(() => (['carta', 'seguro'] as const)[this.pestana()] ?? null);

  protected readonly iniciales = iniciales;
  protected readonly sinOrden = () => 0; // keyvalue sin reordenar: los datos van en el orden del backend
  protected readonly textoEstado = textoEstado;
  protected readonly claseEstado = claseEstado;

  constructor() {
    this.api.periodoActual().subscribe((periodo) => {
      this.periodo.set(periodo);
      this.cargar();
    });
  }

  protected elegir(practica: PracticaPorFormalizar): void {
    this.api.detalle(practica.id).subscribe((detalle) => this.detalle.set(detalle));
  }

  protected descargarPdf(): void {
    const detalle = this.detalle();
    const tipo = this.tipoActual();
    if (!detalle || !tipo) {
      return;
    }
    this.ocupado.set(true);
    this.api.pdf(detalle.id, tipo).subscribe({
      next: (pdf) => {
        this.ocupado.set(false);
        descargar(pdf, NOMBRES_PDF[tipo]);
        this.cargar(detalle.id);
      },
      error: (e: HttpErrorResponse) => this.fallo(e),
    });
  }

  protected enviar(): void {
    const detalle = this.detalle();
    if (!detalle) {
      return;
    }
    this.ocupado.set(true);
    this.api.enviar(detalle.id).subscribe({
      next: ({ destinatarios }) => {
        this.ocupado.set(false);
        this.aviso.open(`Carta y guía enviadas a ${destinatarios.join(' y ')}.`, 'Cerrar', { duration: 6000 });
        this.cargar(detalle.id);
      },
      error: (e: HttpErrorResponse) => this.fallo(e),
    });
  }

  protected acuerdoGuardado(): void {
    this.aviso.open('Acuerdo de colaboración guardado.', 'Cerrar', { duration: 5000 });
    this.cargar(this.detalle()?.id);
  }

  // Recarga la lista y, si hay una práctica elegida, su detalle (los estados cambian al generar o enviar)
  private cargar(seleccion?: number): void {
    const periodo = this.periodo();
    if (!periodo) {
      return;
    }
    this.api.listar(periodo.id).subscribe(({ data }) => {
      this.practicas.set(data);
      const elegida = data.find((p) => p.id === seleccion) ?? (seleccion === undefined ? data[0] : undefined);
      if (elegida) {
        this.elegir(elegida);
      }
    });
  }

  private fallo(e: HttpErrorResponse): void {
    this.ocupado.set(false);
    // Las descargas piden un Blob, así que el mensaje de error llega como Blob y no como JSON
    const mostrar = (texto: string) => this.aviso.open(texto, 'Cerrar', { duration: 6000 });
    if (e.error instanceof Blob) {
      e.error.text().then((t) => mostrar(mensaje(safeJson(t), e.status)));
    } else {
      mostrar(mensaje(e.error, e.status));
    }
  }
}

function safeJson(texto: string): unknown {
  try {
    return JSON.parse(texto);
  } catch {
    return null;
  }
}

function mensaje(cuerpo: unknown, status: number): string {
  const errores = (cuerpo as { errors?: Record<string, string[]> } | null)?.errors;
  if (errores) {
    return Object.values(errores)[0][0];
  }
  return status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado. Inténtalo de nuevo.';
}
