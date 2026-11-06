import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

import { fechasCoherentes } from '../nueva-practica';
import { CalendarioPractica, DIAS_SEMANA } from './asignacion-practicas.model';
import { AsignacionPracticasService } from './asignacion-practicas.service';

function horasCoherentes(control: AbstractControl) {
  const { hora_desde, hora_hasta } = control.value;
  return hora_desde && hora_hasta && hora_hasta <= hora_desde ? { horas: true } : null;
}

// Art. 17: la Coordinadora y el Supervisor(a) acuerdan fechas y horario; aquí se registran.
@Component({
  selector: 'app-fechas-horario',
  imports: [ReactiveFormsModule, MatButtonModule, MatCheckboxModule, MatDialogModule, MatFormFieldModule, MatInputModule],
  templateUrl: './fechas-horario.html',
  styleUrl: './fechas-horario.scss',
})
export class FechasHorario {
  private readonly service = inject(AsignacionPracticasService);
  private readonly referencia = inject(MatDialogRef<FechasHorario, boolean>);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly practica = inject<CalendarioPractica>(MAT_DIALOG_DATA);
  protected readonly dias = DIAS_SEMANA;
  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    fecha_inicio: [this.practica.fecha_inicio ?? '', Validators.required],
    fecha_fin: [this.practica.fecha_fin ?? '', Validators.required],
    dias: [this.practica.horario?.dias ?? ([] as number[]), Validators.required],
    hora_desde: [this.practica.horario?.hora_desde ?? '', Validators.required],
    hora_hasta: [this.practica.horario?.hora_hasta ?? '', Validators.required],
  }, { validators: [fechasCoherentes, horasCoherentes] });

  protected alternarDia(dia: number, marcado: boolean): void {
    const actuales = this.form.controls.dias.value.filter((actual) => actual !== dia);
    const dias = marcado ? [...actuales, dia].sort((a, b) => a - b) : actuales;
    this.form.controls.dias.setValue(dias);
    this.form.controls.dias.markAsTouched();
  }

  protected mensaje(campo: string): string {
    return this.form.get(campo)?.getError('servidor') || 'Completa este campo.';
  }

  protected cancelar(): void {
    if (!this.enviando()) this.referencia.close(false);
  }

  protected guardar(): void {
    if (this.enviando()) return;
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.referencia.disableClose = true;
    this.errorGeneral.set(null);
    this.service.guardarCalendario(this.practica.id, this.form.getRawValue()).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => this.referencia.close(true),
      error: (error: HttpErrorResponse) => {
        this.enviando.set(false);
        this.referencia.disableClose = false;
        const errores = error.error?.errors as Record<string, string[]> | undefined;
        for (const [campo, mensajes] of Object.entries(errores ?? {})) {
          const control = this.form.get(campo);
          if (control) {
            control.setErrors({ servidor: mensajes[0] });
            control.markAsTouched();
          } else {
            this.errorGeneral.set(mensajes[0]);
          }
        }
        if (!errores) this.errorGeneral.set('No se pudieron guardar las fechas y el horario.');
      },
    });
  }
}
