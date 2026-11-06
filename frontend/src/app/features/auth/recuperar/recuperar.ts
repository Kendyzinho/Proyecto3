import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { RouterLink } from '@angular/router';

import { RecuperacionApi } from '../../../core/services/recuperacion-api';

// HU-02: pide el enlace de recuperación al correo institucional
@Component({
  selector: 'app-recuperar',
  imports: [ReactiveFormsModule, RouterLink, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './recuperar.html',
  styleUrls: ['../login/login.scss', './recuperar.scss'],
})
export class Recuperar {
  private readonly api = inject(RecuperacionApi);

  protected readonly form = inject(NonNullableFormBuilder).group({
    email: ['', [Validators.required, Validators.email]],
  });
  protected readonly error = signal<string | null>(null);
  protected readonly enviando = signal(false);
  protected readonly enviadoA = signal<string | null>(null);

  protected enviar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.error.set(null);
    this.enviando.set(true);

    const { email } = this.form.getRawValue();
    this.api.solicitarEnlace(email).subscribe({
      next: () => {
        this.enviando.set(false);
        this.enviadoA.set(email);
      },
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.error.set(mensajeDeError(e.status));
      },
    });
  }
}

function mensajeDeError(status: number): string {
  switch (status) {
    case 0:
      return 'No se pudo conectar con el servidor.';
    case 429:
      return 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.';
    case 422:
      return 'El correo no es válido.';
    default:
      return 'Ocurrió un error inesperado. Inténtalo de nuevo.';
  }
}
