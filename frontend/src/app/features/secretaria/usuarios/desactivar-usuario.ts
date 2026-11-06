import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';

import { Cuenta, UsuariosApi, mensajeDeError } from './usuarios-api';

const MOTIVOS = ['Egresado', 'Retirado', 'Ya no trabaja en la carrera', 'Otro motivo'];

// HU-05: quita el acceso a una cuenta sin borrar sus datos
@Component({
  selector: 'app-desactivar-usuario',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatSelectModule],
  templateUrl: './desactivar-usuario.html',
  styleUrl: './desactivar-usuario.scss',
})
export class DesactivarUsuario {
  private readonly api = inject(UsuariosApi);
  private readonly referencia = inject(MatDialogRef<DesactivarUsuario, boolean>);
  protected readonly cuenta = inject<Cuenta>(MAT_DIALOG_DATA);

  protected readonly motivos = MOTIVOS;
  protected readonly motivo = new FormControl('', { nonNullable: true, validators: Validators.required });
  protected readonly enviando = signal(false);
  protected readonly error = signal<string | null>(null);

  protected confirmar(): void {
    if (this.motivo.invalid) {
      this.motivo.markAsTouched();
      return;
    }
    this.enviando.set(true);
    this.error.set(null);
    this.api.desactivar(this.cuenta.id, this.motivo.value).subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.error.set(mensajeDeError(e));
      },
    });
  }
}
