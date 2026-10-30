import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, OnDestroy, OnInit, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { ErrorStateMatcher } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';

import { NOMBRE_ROL, Usuario } from '../../core/models/usuario';
import { Auth } from '../../core/services/auth';
import { CambioContrasena, PerfilApi } from '../../core/services/perfil-api';
import { validarNuevaClave } from '../../core/validadores/clave';

type CampoClave = keyof CambioContrasena;
const CLAVES_OCULTAS: Record<CampoClave, boolean> = {
  current_password: false, password: false, password_confirmation: false,
};

@Component({
  selector: 'app-perfil',
  imports: [ReactiveFormsModule, RouterLink, MatButtonModule, MatFormFieldModule,
    MatIconModule, MatInputModule, MatProgressSpinnerModule],
  templateUrl: './perfil.html',
  styleUrl: './perfil.scss',
})
export class Perfil implements OnInit, OnDestroy {
  protected readonly auth = inject(Auth);
  private readonly api = inject(PerfilApi);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly roles = NOMBRE_ROL;
  protected readonly usuario = signal<Usuario | null>(null);
  protected readonly cargando = signal(false);
  protected readonly errorCarga = signal<string | null>(null);
  protected readonly guardando = signal(false);
  protected readonly errorGuardado = signal<string | null>(null);
  protected readonly guardado = signal(false);
  protected readonly visibles = signal({ ...CLAVES_OCULTAS });
  protected readonly campos: { clave: CampoClave; etiqueta: string; autocomplete: string }[] = [
    { clave: 'current_password', etiqueta: 'Contraseña actual', autocomplete: 'current-password' },
    { clave: 'password', etiqueta: 'Nueva contraseña', autocomplete: 'new-password' },
    { clave: 'password_confirmation', etiqueta: 'Repite la nueva contraseña', autocomplete: 'new-password' },
  ];
  protected readonly form = inject(NonNullableFormBuilder).group({
    current_password: ['', Validators.required],
    password: ['', [Validators.required, validarNuevaClave]],
    password_confirmation: ['', Validators.required],
  }, { validators: validarRelacionDeClaves });
  protected readonly erroresDeClave: ErrorStateMatcher = {
    isErrorState: (control) => !!(control?.touched && (control.invalid
      || (control === this.form.controls.password && this.form.hasError('reutilizada'))
      || (control === this.form.controls.password_confirmation && this.form.hasError('noCoinciden')))),
  };

  ngOnInit(): void {
    this.cargarPerfil();
  }

  ngOnDestroy(): void {
    this.form.reset();
    this.usuario.set(null);
    this.visibles.set({ ...CLAVES_OCULTAS });
  }

  protected cargarPerfil(): void {
    if (this.cargando()) return;
    this.cargando.set(true);
    this.errorCarga.set(null);
    this.usuario.set(null);
    this.api.consultar().pipe(
      takeUntilDestroyed(this.destroyRef),
      finalize(() => this.cargando.set(false)),
    ).subscribe({
      next: (usuario) => this.usuario.set(usuario),
      error: () => this.errorCarga.set('No pudimos cargar tus datos. Comprueba la conexión e inténtalo de nuevo.'),
    });
  }

  protected alternarVisibilidad(campo: CampoClave): void {
    this.visibles.update((actual) => ({ ...actual, [campo]: !actual[campo] }));
  }

  protected guardar(): void {
    if (this.guardando() || !this.usuario()) return;
    this.guardado.set(false);
    this.errorGuardado.set(null);
    this.form.markAllAsTouched();
    if (this.form.invalid) return;

    const datos = this.form.getRawValue();
    this.guardando.set(true);
    this.form.disable({ emitEvent: false });
    this.api.cambiarContrasena(datos).pipe(
      takeUntilDestroyed(this.destroyRef),
      finalize(() => {
        if (this.form.disabled) this.form.enable({ emitEvent: false });
        this.guardando.set(false);
      }),
    ).subscribe({
      next: () => {
        this.form.reset();
        this.visibles.set({ ...CLAVES_OCULTAS });
        this.guardado.set(true);
      },
      error: (error: unknown) => {
        // Se habilita antes de asignar errores para que enable() no los sustituya.
        this.form.enable({ emitEvent: false });
        if (error instanceof HttpErrorResponse && error.status === 422) {
          const errores = error.error?.errors as Partial<Record<CampoClave, string[]>> | undefined;
          for (const { clave } of this.campos) {
            const mensaje = errores?.[clave]?.[0];
            if (typeof mensaje === 'string') {
              this.form.controls[clave].setErrors({ servidor: mensaje });
            }
          }
          this.errorGuardado.set('Revisa los campos indicados antes de volver a guardar.');
        } else if (error instanceof HttpErrorResponse && error.status === 429) {
          this.errorGuardado.set('Demasiados intentos. Espera un minuto antes de volver a intentarlo.');
        } else {
          this.errorGuardado.set('No pudimos confirmar el cambio. Comprueba tu conexión; si el problema continúa, verifica tu contraseña al iniciar sesión.');
        }
      },
    });
  }

  protected mensajeCampo(campo: CampoClave): string {
    const control = this.form.controls[campo];
    if (control.hasError('servidor')) return control.getError('servidor');
    if (control.hasError('required')) return 'Completa este campo.';
    if (control.hasError('longitud')) return 'Usa al menos 8 caracteres.';
    if (control.hasError('complejidad')) return 'Incluye una letra mayúscula y un número.';
    if (control.hasError('demasiadoLarga')) return 'La nueva contraseña es demasiado larga. Usa una más corta.';
    if (control.hasError('caracterInvalido')) return 'La nueva contraseña contiene un carácter no permitido.';
    if (campo === 'password' && this.form.hasError('reutilizada')) return 'La nueva contraseña debe ser distinta de la actual.';
    if (campo === 'password_confirmation' && this.form.hasError('noCoinciden')) return 'Las contraseñas no coinciden.';
    return '';
  }
}

function validarRelacionDeClaves(control: AbstractControl): ValidationErrors | null {
  const { current_password, password, password_confirmation } = control.value as CambioContrasena;
  const errores: ValidationErrors = {};
  if (password && password === current_password) errores['reutilizada'] = true;
  if (password_confirmation && password !== password_confirmation) errores['noCoinciden'] = true;
  return Object.keys(errores).length ? errores : null;
}
