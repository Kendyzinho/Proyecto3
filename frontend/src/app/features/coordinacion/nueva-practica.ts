import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { AbstractControl, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatRadioModule } from '@angular/material/radio';
import { MatSelectModule } from '@angular/material/select';
import { CatalogoExcepcional, PeriodoPractica, PracticasApi } from './practicas-api';

export function fechasCoherentes(control: AbstractControl) {
  const { fecha_inicio, fecha_fin } = control.value;
  return fecha_inicio && fecha_fin && fecha_fin < fecha_inicio ? { fechas: true } : null;
}

@Component({
  selector: 'app-nueva-practica',
  imports: [ReactiveFormsModule, MatButtonModule, MatDialogModule, MatFormFieldModule, MatInputModule, MatRadioModule, MatSelectModule],
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
  private readonly avisoCupo = signal<{ mensaje: string; habilitacion_id: number; supervisor_id: number } | null>(null);
  protected readonly form = inject(NonNullableFormBuilder).group({
    habilitacion_id: [0, Validators.min(1)],
    supervisor_id: [0, Validators.min(1)],
    modalidad: ['presencial', [Validators.required, Validators.pattern(/^(presencial|a_distancia|mixta)$/)]],
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

  protected practica(): string {
    const habilitacion = this.catalogo()?.habilitaciones.find((h) => h.id === this.form.controls.habilitacion_id.value);
    if (!habilitacion) return '';
    return habilitacion.horas ? `${habilitacion.tipo_practica} (${habilitacion.horas} h)` : habilitacion.tipo_practica;
  }

  protected mensaje(campo: string): string {
    const control = this.form.get(campo);
    return control?.getError('servidor') || (control?.hasError('maxlength') ? 'Máximo 5000 caracteres.' : 'Completa este campo con un valor válido.');
  }

  // El aviso vale para el estudiante y el supervisor(a) con que se intentó; si cambian, hay que volver a comprobar
  protected mensajeCupo(): string | null {
    const aviso = this.avisoCupo();
    const { habilitacion_id, supervisor_id } = this.form.getRawValue();
    return aviso && aviso.habilitacion_id === habilitacion_id && aviso.supervisor_id === supervisor_id ? aviso.mensaje : null;
  }

  protected cancelar(): void { if (!this.enviando()) this.referencia.close(false); }

  protected guardar(): void {
    if (this.enviando() || this.cargando() || this.errorCarga()) return;
    if (this.form.invalid) { this.form.markAllAsTouched(); return; }
    const datos = this.form.getRawValue();
    const excederCupo = this.mensajeCupo() !== null;
    this.enviando.set(true);
    this.form.disable();
    this.referencia.disableClose = true;
    this.errorGeneral.set(null);
    this.api.registrar({ ...datos, periodo_id: this.periodo.id, ...(excederCupo ? { exceder_cupo: true } : {}) }).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.form.enable();
        this.referencia.disableClose = false;
        const errores = e.error?.errors as Record<string, string[]> | undefined;
        if (e.status === 422 && errores) {
          for (const [campo, mensajes] of Object.entries(errores)) {
            if (campo === 'exceder_cupo') {
              this.avisoCupo.set({ mensaje: mensajes[0], habilitacion_id: datos.habilitacion_id, supervisor_id: datos.supervisor_id });
              continue;
            }
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
