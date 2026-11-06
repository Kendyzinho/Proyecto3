import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';

import { Rol } from '../../../core/models/usuario';
import { Cuenta, DatosCuenta, ROLES, UsuariosApi } from './usuarios-api';

// HU-05: alta y edición de una cuenta con su rol. Recibe la cuenta a editar, o null para crear
@Component({
  selector: 'app-formulario-usuario',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatSelectModule],
  templateUrl: './formulario-usuario.html',
  styleUrl: './formulario-usuario.scss',
})
export class FormularioUsuario {
  private readonly api = inject(UsuariosApi);
  private readonly referencia = inject(MatDialogRef<FormularioUsuario, boolean>);
  protected readonly cuenta = inject<Cuenta | null>(MAT_DIALOG_DATA);

  protected readonly form = inject(NonNullableFormBuilder).group({
    rol: [this.cuenta?.rol ?? ('estudiante' as Rol)],
    name: [this.cuenta?.name ?? '', Validators.required],
    rut: [this.cuenta?.rut ?? ''],
    email: [this.cuenta?.email ?? '', [Validators.required, Validators.email]],
    anio_ingreso: [this.cuenta?.anio_ingreso ?? new Date().getFullYear()],
  });

  // Al editar, un estudiante sigue siendo estudiante y el personal solo cambia entre roles del personal
  protected readonly roles = ROLES.filter(
    (r) => !this.cuenta || (r.valor === 'estudiante') === (this.cuenta.rol === 'estudiante'),
  );

  private readonly rol = toSignal(this.form.controls.rol.valueChanges, { initialValue: this.form.controls.rol.value });
  protected readonly esEstudiante = computed(() => this.rol() === 'estudiante');

  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);

  constructor() {
    if (this.cuenta?.rol === 'estudiante') {
      this.form.controls.rol.disable();
    }
  }

  protected guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const valor = this.form.getRawValue();
    const datos: DatosCuenta = {
      ...valor,
      rut: valor.rut.trim() || null,
      anio_ingreso: valor.rol === 'estudiante' ? valor.anio_ingreso : null,
    };

    this.enviando.set(true);
    this.errorGeneral.set(null);
    const peticion = this.cuenta ? this.api.editar(this.cuenta.id, datos) : this.api.crear(datos);
    peticion.subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.mostrarErrores(e);
      },
    });
  }

  // Los mensajes del backend llegan por campo ("El RUT no es válido.", "El correo pertenece a otra cuenta."...)
  private mostrarErrores(e: HttpErrorResponse): void {
    const errores = e.error?.errors as Record<string, string[]> | undefined;
    if (e.status !== 422 || !errores) {
      this.errorGeneral.set(e.status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado.');
      return;
    }
    for (const [campo, mensajes] of Object.entries(errores)) {
      const control = this.form.get(campo);
      if (control && control.enabled) {
        control.setErrors({ servidor: mensajes[0] });
        control.markAsTouched();
      } else {
        this.errorGeneral.set(mensajes[0]);
      }
    }
  }
}
