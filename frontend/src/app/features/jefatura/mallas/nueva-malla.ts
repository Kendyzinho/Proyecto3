import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { MallasApi } from './mallas-api';

// HU-06: crea una malla en preparación, sin prácticas
@Component({
  selector: 'app-nueva-malla',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatInputModule],
  templateUrl: './nueva-malla.html',
  styleUrl: './nueva-malla.scss',
})
export class NuevaMalla {
  private readonly api = inject(MallasApi);
  private readonly referencia = inject(MatDialogRef<NuevaMalla, boolean>);

  protected readonly form = inject(NonNullableFormBuilder).group({
    nombre: ['', Validators.required],
  });
  protected readonly enviando = signal(false);
  protected readonly error = signal<string | null>(null);

  protected crear(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.error.set(null);
    this.api.crear(this.form.getRawValue().nombre).subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.error.set(mensajeDeError(e));
      },
    });
  }
}
