import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';

import { NominaApi, Periodo, Practica } from './nomina-api';

export interface DatosAgregarEstudiante {
  periodo: Periodo;
  practicas: Practica[];
}

// HU-07: carga individual de un estudiante (pantalla 07b). Las reglas las revisa el backend
@Component({
  selector: 'app-agregar-estudiante',
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
  ],
  templateUrl: './agregar-estudiante.html',
  styleUrl: './agregar-estudiante.scss',
})
export class AgregarEstudiante {
  private readonly api = inject(NominaApi);
  private readonly referencia = inject(MatDialogRef<AgregarEstudiante, boolean>);
  protected readonly datos = inject<DatosAgregarEstudiante>(MAT_DIALOG_DATA);

  protected readonly form = inject(NonNullableFormBuilder).group({
    nombre: ['', Validators.required],
    rut: ['', Validators.required],
    correo: ['', [Validators.required, Validators.email]],
    tipo_practica_id: [0, Validators.min(1)],
    anio_ingreso: [new Date().getFullYear(), Validators.required],
    cumple_prerrequisito: [true],
  });
  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);

  protected guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.errorGeneral.set(null);

    this.api.agregar({ ...this.form.getRawValue(), periodo_id: this.datos.periodo.id }).subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.mostrarErrores(e);
      },
    });
  }

  // Los mensajes del backend llegan por campo ("Correo no institucional", "RUT inválido"...)
  private mostrarErrores(e: HttpErrorResponse): void {
    const errores = e.error?.errors as Record<string, string[]> | undefined;
    if (e.status !== 422 || !errores) {
      this.errorGeneral.set(e.status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado.');
      return;
    }
    for (const [campo, mensajes] of Object.entries(errores)) {
      const control = this.form.get(campo);
      if (control) {
        control.setErrors({ servidor: mensajes[0] });
        control.markAsTouched();
      } else {
        this.errorGeneral.set(mensajes[0]);
      }
    }
  }
}
