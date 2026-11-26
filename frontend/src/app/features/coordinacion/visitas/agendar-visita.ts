import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatRadioModule } from '@angular/material/radio';
import { MatSelectModule } from '@angular/material/select';

import { PROVEEDORES_FECHA, fechaIso } from '../../../core/i18n/fechas';
import { CentroVisita, ModalidadVisita, PracticaDisponible, PracticaVisita, Visita, VisitasApi } from './visitas-api';

// Para editar llega la visita; para agendar desde la alerta, el centro y sus prácticas sin visita
export interface DatosDialogoVisita {
  visita?: Visita;
  centroId?: number;
  practicas?: number[];
}

// HU-15: fecha, hora, modalidad y prácticas que se revisan en la visita
@Component({
  selector: 'app-agendar-visita',
  imports: [
    ReactiveFormsModule, MatButtonModule, MatCheckboxModule, MatDatepickerModule, MatDialogModule,
    MatFormFieldModule, MatInputModule, MatRadioModule, MatSelectModule,
  ],
  providers: PROVEEDORES_FECHA,
  templateUrl: './agendar-visita.html',
  styleUrl: './agendar-visita.scss',
})
export class AgendarVisita {
  private readonly api = inject(VisitasApi);
  private readonly referencia = inject(MatDialogRef<AgendarVisita, Visita | undefined>);
  private readonly destroyRef = inject(DestroyRef);
  protected readonly datos = inject<DatosDialogoVisita>(MAT_DIALOG_DATA);
  protected readonly visita = this.datos.visita;

  protected readonly disponibles = signal<PracticaDisponible[]>([]);
  protected readonly cargando = signal(true);
  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    centro: [this.visita?.centro?.id ?? this.datos.centroId ?? (null as number | null), Validators.required],
    practicas: [this.visita?.practicas.map((p) => p.id) ?? this.datos.practicas ?? ([] as number[]), Validators.required],
    fecha: [(this.visita?.fecha_hora.slice(0, 10) ?? '') as string | Date, Validators.required],
    hora: [this.visita?.fecha_hora.slice(11, 16) ?? '', Validators.required],
    modalidad: [this.visita?.modalidad ?? ('terreno' as ModalidadVisita), Validators.required],
  });

  private readonly centroElegido = toSignal(this.form.controls.centro.valueChanges, { initialValue: this.form.controls.centro.value });

  protected readonly centros = computed<CentroVisita[]>(() => {
    const porId = new Map<number, CentroVisita>();
    for (const practica of this.disponibles()) porId.set(practica.centro.id, practica.centro);
    if (this.visita?.centro) porId.set(this.visita.centro.id, this.visita.centro);
    return [...porId.values()].sort((a, b) => a.nombre.localeCompare(b.nombre));
  });

  // Las de la visita siempre aparecen, aunque ya no estén disponibles (una pudo finalizar)
  protected readonly practicasDelCentro = computed<PracticaVisita[]>(() => {
    const centro = this.centroElegido();
    const porId = new Map<number, PracticaVisita>();
    if (this.visita?.centro?.id === centro) {
      for (const practica of this.visita.practicas) porId.set(practica.id, practica);
    }
    for (const practica of this.disponibles()) {
      if (practica.centro.id === centro) porId.set(practica.id, practica);
    }
    return [...porId.values()].sort((a, b) => a.estudiante.localeCompare(b.estudiante));
  });

  constructor() {
    this.api.practicasDisponibles().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (practicas) => {
        this.disponibles.set(practicas);
        this.cargando.set(false);
      },
      error: () => {
        this.cargando.set(false);
        this.errorGeneral.set('No se pudieron cargar las prácticas.');
      },
    });
    // Al cambiar de centro, las prácticas elegidas del centro anterior ya no sirven
    this.form.controls.centro.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.form.controls.practicas.setValue([]);
    });
  }

  protected elegida(id: number): boolean {
    return this.form.controls.practicas.value.includes(id);
  }

  protected alternar(id: number, marcada: boolean): void {
    const otras = this.form.controls.practicas.value.filter((actual) => actual !== id);
    this.form.controls.practicas.setValue(marcada ? [...otras, id] : otras);
    this.form.controls.practicas.markAsTouched();
  }

  protected mensaje(campo: string): string {
    return this.form.get(campo)?.getError('servidor') || 'Completa este campo.';
  }

  protected cancelar(): void {
    if (!this.enviando()) this.referencia.close();
  }

  protected guardar(): void {
    if (this.enviando()) return;
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const { practicas, fecha, hora, modalidad } = this.form.getRawValue();
    // Texto local, no toISOString(): el servidor trabaja en hora de Chile
    const datos = { fecha_hora: `${fechaIso(fecha)} ${hora}`, modalidad, practicas };
    this.enviando.set(true);
    this.referencia.disableClose = true;
    this.errorGeneral.set(null);
    const peticion = this.visita ? this.api.editar(this.visita.id, datos) : this.api.crear(datos);
    peticion.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (visita) => this.referencia.close(visita),
      error: (error: HttpErrorResponse) => {
        this.enviando.set(false);
        this.referencia.disableClose = false;
        const errores = error.error?.errors as Record<string, string[]> | undefined;
        if (!errores) {
          this.errorGeneral.set('No se pudo guardar la visita.');
          return;
        }
        // Solo centro, fecha y hora tienen mat-error; lo demás se muestra abajo del formulario
        const conCampo: Record<string, 'centro' | 'fecha' | 'hora'> = { centro: 'centro', fecha_hora: 'fecha', hora: 'hora' };
        for (const [campo, mensajes] of Object.entries(errores)) {
          const destino = conCampo[campo];
          if (destino) {
            this.form.controls[destino].setErrors({ servidor: mensajes[0] });
            this.form.controls[destino].markAsTouched();
          } else {
            this.errorGeneral.set(mensajes[0]);
          }
        }
      },
    });
  }
}
