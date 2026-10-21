import { HttpErrorResponse } from '@angular/common/http';
import { Component, effect, inject, input, output, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

import { descargar } from '../nomina/nomina-api';
import { Acuerdo, DocumentosApi, TEXTO_ACUERDO } from './documentos-api';

// HU-10: acuerdo de colaboración firmado del centro, con su vigencia (pestaña "Acuerdo")
@Component({
  selector: 'app-acuerdo-centro',
  imports: [ReactiveFormsModule, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule, MatSelectModule],
  templateUrl: './acuerdo-centro.html',
  styleUrl: './acuerdo-centro.scss',
})
export class AcuerdoCentro {
  readonly centroId = input.required<number>();
  readonly acuerdo = input<Acuerdo | null>(null);
  readonly guardado = output<void>();

  private readonly api = inject(DocumentosApi);
  protected readonly textoAcuerdo = TEXTO_ACUERDO;
  protected readonly form = inject(NonNullableFormBuilder).group({
    tipo: ['tradicional' as Acuerdo['tipo'], Validators.required],
    representante_legal: ['', Validators.required],
    fecha_inicio: [''],
    fecha_termino: [''],
  });
  protected readonly archivo = signal<File | null>(null);
  protected readonly guardando = signal(false);
  protected readonly error = signal<string | null>(null);

  constructor() {
    // Cada vez que cambia la práctica elegida, el formulario muestra el acuerdo de su centro
    effect(() => {
      const acuerdo = this.acuerdo();
      this.form.reset({
        tipo: acuerdo?.tipo ?? 'tradicional',
        representante_legal: acuerdo?.representante_legal ?? '',
        fecha_inicio: acuerdo?.fecha_inicio ?? '',
        fecha_termino: acuerdo?.fecha_termino ?? '',
      });
      this.archivo.set(null);
      this.error.set(null);
    });
  }

  protected elegirArchivo(evento: Event): void {
    const entrada = evento.target as HTMLInputElement;
    this.archivo.set(entrada.files?.[0] ?? null);
    entrada.value = '';
  }

  protected verPdf(): void {
    const acuerdo = this.acuerdo();
    if (acuerdo) {
      this.api.archivoAcuerdo(acuerdo.id).subscribe((pdf) => descargar(pdf, 'Acuerdo de colaboración.pdf'));
    }
  }

  protected guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.error.set(null);
    this.api.guardarAcuerdo(this.centroId(), { ...this.form.getRawValue(), archivo: this.archivo() }).subscribe({
      next: () => {
        this.guardando.set(false);
        this.guardado.emit();
      },
      error: (e: HttpErrorResponse) => {
        this.guardando.set(false);
        const errores = e.error?.errors as Record<string, string[]> | undefined;
        this.error.set(errores ? Object.values(errores)[0][0] : 'No se pudo guardar el acuerdo.');
      },
    });
  }
}
