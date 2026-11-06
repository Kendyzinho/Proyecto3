import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { ErrorStateMatcher } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { RecuperacionApi } from '../../../core/services/recuperacion-api';
import { validarNuevaClave } from '../../../core/validadores/clave';

type Campo = 'password' | 'password_confirmation';
type Estado = 'formulario' | 'listo' | 'enlace_invalido';

// HU-02: define la contraseña nueva con el enlace que llegó al correo (vence a las 24 horas)
@Component({
  selector: 'app-restablecer',
  imports: [ReactiveFormsModule, RouterLink, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './restablecer.html',
  styleUrls: ['../login/login.scss', '../recuperar/recuperar.scss'],
})
export class Restablecer {
  private readonly api = inject(RecuperacionApi);
  private readonly parametros = inject(ActivatedRoute).snapshot.queryParamMap;

  protected readonly token = this.parametros.get('token') ?? '';
  protected readonly email = this.parametros.get('email') ?? '';
  protected readonly estado = signal<Estado>(this.token && this.email ? 'formulario' : 'enlace_invalido');
  protected readonly error = signal<string | null>(null);
  protected readonly guardando = signal(false);
  protected readonly visibles = signal<Record<Campo, boolean>>({ password: false, password_confirmation: false });

  protected readonly form = inject(NonNullableFormBuilder).group({
    password: ['', [Validators.required, validarNuevaClave]],
    password_confirmation: ['', Validators.required],
  }, { validators: validarConfirmacion });
  protected readonly erroresDeClave: ErrorStateMatcher = {
    isErrorState: (control) => !!(control?.touched && (control.invalid
      || (control === this.form.controls.password_confirmation && this.form.hasError('noCoinciden')))),
  };

  protected alternarVisibilidad(campo: Campo): void {
    this.visibles.update((v) => ({ ...v, [campo]: !v[campo] }));
  }

  protected guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.error.set(null);
    this.guardando.set(true);

    this.api.restablecer({ token: this.token, email: this.email, ...this.form.getRawValue() }).subscribe({
      next: () => this.estado.set('listo'),
      error: (e: HttpErrorResponse) => {
        this.guardando.set(false);
        const errores = (e.error?.errors ?? {}) as Record<string, string[]>;
        if (e.status === 422 && (errores['token'] || errores['email'])) {
          this.estado.set('enlace_invalido');
        } else if (e.status === 422 && errores['password']) {
          this.form.controls.password.setErrors({ servidor: errores['password'][0] });
        } else {
          this.error.set(mensajeDeError(e.status));
        }
      },
    });
  }

  protected mensajeCampo(campo: Campo): string {
    const control = this.form.controls[campo];
    if (control.hasError('servidor')) return control.getError('servidor');
    if (control.hasError('required')) return campo === 'password' ? 'Ingresa la nueva contraseña.' : 'Repite la nueva contraseña.';
    if (control.hasError('longitud')) return 'Usa al menos 8 caracteres.';
    if (control.hasError('complejidad')) return 'Incluye una letra mayúscula y un número.';
    if (control.hasError('demasiadoLarga')) return 'La nueva contraseña es demasiado larga. Usa una más corta.';
    if (control.hasError('caracterInvalido')) return 'La nueva contraseña contiene un carácter no permitido.';
    if (campo === 'password_confirmation' && this.form.hasError('noCoinciden')) return 'Las contraseñas no coinciden.';
    return '';
  }
}

function validarConfirmacion(control: AbstractControl): ValidationErrors | null {
  const { password, password_confirmation } = control.value as Record<Campo, string>;
  return password_confirmation && password !== password_confirmation ? { noCoinciden: true } : null;
}

function mensajeDeError(status: number): string {
  switch (status) {
    case 0:
      return 'No se pudo conectar con el servidor.';
    case 429:
      return 'Demasiados intentos. Espera un minuto antes de volver a intentarlo.';
    default:
      return 'Ocurrió un error inesperado. Inténtalo de nuevo.';
  }
}
