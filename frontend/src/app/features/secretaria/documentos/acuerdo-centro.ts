import { HttpErrorResponse } from '@angular/common/http';
import { Component, effect, inject, input, output, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

import { PROVEEDORES_FECHA, fechaIso } from '../../../core/i18n/fechas';
import { descargar } from '../nomina/nomina-api';
import { Acuerdo, DocumentosApi, TEXTO_ACUERDO, abrirPdf } from './documentos-api';

// HU-10: acuerdo de colaboración firmado del centro, con su vigencia (diálogo de Documentos y de Centros de práctica)
@Component({
  selector: 'app-acuerdo-centro',
  imports: [ReactiveFormsModule, MatButtonModule, MatDatepickerModule, MatFormFieldModule, MatIconModule, MatInputModule, MatSelectModule],
  providers: PROVEEDORES_FECHA,
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
    fecha_inicio: ['' as string | Date],
    fecha_termino: ['' as string | Date],
  });
  protected readonly archivo = signal<File | null>(null);
  protected readonly guardando = signal(false);
  protected readonly generando = signal(false);
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
    if (!acuerdo?.tiene_archivo) return;
    abrirPdf(this.api.archivoAcuerdo(acuerdo.id), (mensaje) => this.error.set(mensaje));
  }

  // El acuerdo lleva tipo, representante y vigencia; el PDF firmado se sube después con "Guardar acuerdo"
  protected descargarParaFirmar(): void {
    const { tipo, representante_legal, fecha_inicio, fecha_termino } = this.form.controls;
    // Para generarlo hacen falta las fechas, aunque al guardar sean opcionales (un acuerdo en trámite aún no las tiene)
    for (const fecha of [fecha_inicio, fecha_termino]) {
      if (!fecha.value) fecha.setErrors({ required: true });
    }
    const campos = [tipo, representante_legal, fecha_inicio, fecha_termino];
    if (campos.some((campo) => campo.invalid)) {
      campos.forEach((campo) => campo.markAsTouched());
      return;
    }
    this.generando.set(true);
    this.error.set(null);
    this.api.borradorAcuerdo(this.centroId(), {
      tipo: tipo.value, representante_legal: representante_legal.value,
      fecha_inicio: fechaIso(fecha_inicio.value), fecha_termino: fechaIso(fecha_termino.value),
    }).subscribe({
      next: (pdf) => {
        this.generando.set(false);
        descargar(pdf, 'Acuerdo de colaboración para firmar.pdf');
      },
      error: (e: HttpErrorResponse) => {
        this.generando.set(false);
        // La respuesta es un Blob: el mensaje de validación (ej. término antes del inicio) hay que leerlo como texto
        if (e.status === 422 && e.error instanceof Blob) {
          e.error.text().then((texto) => {
            let errores: Record<string, string[]> | undefined;
            try {
              errores = JSON.parse(texto)?.errors;
            } catch {
              errores = undefined;
            }
            this.error.set(errores ? Object.values(errores)[0][0] : 'No se pudo generar el acuerdo.');
          });
          return;
        }
        this.error.set('No se pudo generar el acuerdo. Inténtalo otra vez.');
      },
    });
  }

  protected guardar(): void {
    // Quita el "falta la fecha" que pudo dejar Descargar: al guardar, las fechas son opcionales
    this.form.controls.fecha_inicio.updateValueAndValidity();
    this.form.controls.fecha_termino.updateValueAndValidity();
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.guardando.set(true);
    this.error.set(null);
    const datos = this.form.getRawValue();
    this.api.guardarAcuerdo(this.centroId(), {
      ...datos, fecha_inicio: fechaIso(datos.fecha_inicio), fecha_termino: fechaIso(datos.fecha_termino), archivo: this.archivo(),
    }).subscribe({
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
