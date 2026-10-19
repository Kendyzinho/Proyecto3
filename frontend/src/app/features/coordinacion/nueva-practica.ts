import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { CatalogoExcepcional, PeriodoPractica, PracticasApi } from './practicas-api';

export function fechasCoherentes(control: AbstractControl) {
  const { fecha_inicio, fecha_fin } = control.value;
  return fecha_inicio && fecha_fin && fecha_fin < fecha_inicio ? { fechas: true } : null;
}

@Component({
  selector: 'app-nueva-practica',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatIconModule, MatInputModule, MatSelectModule],
  templateUrl: './nueva-practica.html',
  styleUrl: './nueva-practica.scss',
})
export class NuevaPractica {
  private readonly api = inject(PracticasApi);
  private readonly referencia = inject(MatDialogRef<NuevaPractica, boolean>);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly periodo = inject<PeriodoPractica>(MAT_DIALOG_DATA);
  protected readonly catalogo = signal<CatalogoExcepcional | null>(null);
  protected readonly cargando = signal(false);
  protected readonly errorCarga = signal(false);
  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    habilitacion_id: [0, Validators.min(1)],
    supervisor_id: [0, Validators.min(1)],
    modalidad: ['presencial', [Validators.required, Validators.pattern(/^(presencial|mixta)$/)]],
    fecha_inicio: ['', Validators.required],
    fecha_fin: ['', Validators.required],
    justificacion_excepcional: ['', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(5000)]],
  }, { validators: fechasCoherentes });

  constructor() { this.cargar(); }

  protected cargar(): void {
    this.cargando.set(true);
    this.errorCarga.set(false);
    this.api.catalogo(this.periodo.id).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (catalogo) => { this.catalogo.set(catalogo); this.cargando.set(false); },
      error: () => { this.errorCarga.set(true); this.cargando.set(false); },
    });
  }

  protected centro(): string {
    return this.catalogo()?.supervisores.find((s) => s.id === this.form.controls.supervisor_id.value)?.centro || '';
  }

  protected mensaje(campo: string): string {
    const control = this.form.get(campo);
    return control?.getError('servidor') || (control?.hasError('maxlength') ? 'Máximo 5000 caracteres.' : 'Completa este campo con un valor válido.');
  }

  protected cancelar(): void { if (!this.enviando()) this.referencia.close(false); }

  protected guardar(): void {
    if (this.enviando() || this.cargando() || this.errorCarga()) return;
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    this.enviando.set(true);
    this.form.disable();
    this.referencia.disableClose = true;
    this.errorGeneral.set(null);
    this.api.registrar({ ...this.form.getRawValue(), periodo_id: this.periodo.id }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.form.enable();
        this.referencia.disableClose = false;
        const errores = e.error?.errors as Record<string, string[]> | undefined;
        if (e.status === 422 && errores) {
          for (const [campo, mensajes] of Object.entries(errores)) {
            const control = this.form.get(campo);
            if (control) { control.setErrors({ servidor: mensajes[0] }); control.markAsTouched(); }
            else this.errorGeneral.set(mensajes[0]);
          }
        } else {
          this.errorGeneral.set(e.status === 0 ? 'No se pudo confirmar el registro. Comprueba la conexión antes de reintentar.' : 'No se pudo registrar la práctica. Inténtalo nuevamente.');
        }
      },
    });
  }
}
