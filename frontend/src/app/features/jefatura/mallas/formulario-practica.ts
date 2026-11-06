import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { CATEGORIAS, Categoria, DatosPractica, Malla, MallasApi, PracticaMalla } from './mallas-api';

export interface DatosFormularioPractica {
  malla: Malla;
  practica: PracticaMalla | null; // null = agregar
}

export type ResultadoPractica = 'agregada' | 'guardada' | 'quitada';

// HU-06: nombre, categoría, horas y prerrequisito de una práctica de la malla
@Component({
  selector: 'app-formulario-practica',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule],
  templateUrl: './formulario-practica.html',
  styleUrl: './formulario-practica.scss',
})
export class FormularioPractica {
  private readonly api = inject(MallasApi);
  private readonly referencia = inject(MatDialogRef<FormularioPractica, ResultadoPractica>);
  private readonly datos = inject<DatosFormularioPractica>(MAT_DIALOG_DATA);

  protected readonly malla = this.datos.malla;
  protected readonly practica = this.datos.practica;
  // Solo a la malla en preparación se le quitan prácticas
  protected readonly enPreparacion = this.malla.estado === 'en_preparacion';
  protected readonly categorias = CATEGORIAS;

  protected readonly form = inject(NonNullableFormBuilder).group({
    nombre: [this.practica?.nombre ?? '', Validators.required],
    categoria: [this.practica?.categoria ?? ('laboral' as Categoria), Validators.required],
    horas_requeridas: [
      this.practica?.horas_requeridas ?? (null as number | null),
      [Validators.required, Validators.min(1), Validators.max(1000)],
    ],
    prerrequisito: [this.practica?.prerrequisito ?? ''],
  });

  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);

  protected guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const valor = this.form.getRawValue();
    const datos: DatosPractica = {
      ...valor,
      horas_requeridas: valor.horas_requeridas!,
      prerrequisito: valor.prerrequisito.trim() || null,
    };

    this.enviando.set(true);
    this.errorGeneral.set(null);
    const peticion = this.practica
      ? this.api.editarPractica(this.practica.id, datos)
      : this.api.agregarPractica(this.malla.id, datos);
    peticion.subscribe({
      next: () => this.referencia.close(this.practica ? 'guardada' : 'agregada'),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.mostrarErrores(e);
      },
    });
  }

  protected quitar(): void {
    this.enviando.set(true);
    this.errorGeneral.set(null);
    this.api.quitarPractica(this.practica!.id).subscribe({
      next: () => this.referencia.close('quitada'),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.errorGeneral.set(mensajeDeError(e));
      },
    });
  }

  // Los mensajes del backend llegan por campo ("La malla ya tiene una práctica con ese nombre."...)
  private mostrarErrores(e: HttpErrorResponse): void {
    const errores = e.error?.errors as Record<string, string[]> | undefined;
    if (e.status !== 422 || !errores) {
      this.errorGeneral.set(mensajeDeError(e));
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
