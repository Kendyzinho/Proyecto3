import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressBarModule } from '@angular/material/progress-bar';

import { DocumentosApi, TipoDocumento } from '../../secretaria/documentos/documentos-api';
import { FichaEstudianteApi } from './ficha-estudiante-api';
import { FichaEstudianteDatos } from './ficha-estudiante.model';

@Component({
  selector: 'app-ficha-estudiante',
  imports: [DatePipe, MatButtonModule, MatDialogModule, MatProgressBarModule],
  templateUrl: './ficha-estudiante.html',
  styleUrl: './ficha-estudiante.scss',
})
export class FichaEstudiante {
  private readonly api = inject(FichaEstudianteApi);
  private readonly documentosApi = inject(DocumentosApi);
  private readonly ref = inject(MatDialogRef<FichaEstudiante>);
  private readonly practicaId = inject<number>(MAT_DIALOG_DATA);
  protected readonly datos = signal<FichaEstudianteDatos | null>(null);
  protected readonly cargando = signal(true);
  protected readonly error = signal('');
  protected readonly descargando = signal<TipoDocumento | null>(null);

  constructor() {
    this.api.consultar(this.practicaId).subscribe({
      next: ({ data }) => { this.datos.set(data); this.cargando.set(false); },
      error: () => { this.error.set('No se pudo cargar la ficha. Intenta nuevamente.'); this.cargando.set(false); },
    });
  }

  protected descargar(tipo: TipoDocumento): void {
    if (this.descargando()) return;
    this.descargando.set(tipo);
    this.documentosApi.pdfFicha(this.practicaId, tipo).subscribe({
      next: (archivo) => {
        const enlace = URL.createObjectURL(archivo);
        const elemento = document.createElement('a');
        elemento.href = enlace;
        elemento.download = `${tipo}-practica-${this.practicaId}.pdf`;
        elemento.click();
        URL.revokeObjectURL(enlace);
        this.descargando.set(null);
      },
      error: () => { this.error.set('No se pudo descargar el documento.'); this.descargando.set(null); },
    });
  }

  protected cerrar(): void { this.ref.close(); }
}
