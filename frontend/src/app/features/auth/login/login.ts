import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { Router, RouterLink } from '@angular/router';

import { Auth } from '../../../core/services/auth';

// HU-01: inicio de sesión con credenciales institucionales y redirección según el rol
@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule, RouterLink, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './login.html',
  styleUrl: './login.scss',
})
export class Login {
  private readonly auth = inject(Auth);
  private readonly router = inject(Router);

  protected readonly form = inject(NonNullableFormBuilder).group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });
  protected readonly error = signal<string | null>(null);
  protected readonly enviando = signal(false);
  protected readonly verClave = signal(false);

  protected ingresar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.error.set(null);
    this.enviando.set(true);

    const { email, password } = this.form.getRawValue();
    this.auth.login(email, password).subscribe({
      next: () => this.router.navigateByUrl(this.auth.inicio()),
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
    case 403:
      return 'Tu cuenta está desactivada. Escribe a la Secretaría de Jefatura.';
    case 401:
    case 422:
      return 'Correo o contraseña incorrectos. Revisa tus datos e inténtalo de nuevo.';
    default:
      return 'Ocurrió un error inesperado. Inténtalo de nuevo.';
  }
}
