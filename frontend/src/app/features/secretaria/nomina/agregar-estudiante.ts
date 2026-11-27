import { HttpErrorResponse } from '@angular/common/http';
import { Component, DestroyRef, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { Subject, catchError, debounceTime, of, switchMap } from 'rxjs';

import { Area, EstudianteRegistrado, NominaApi, Periodo, Practica } from './nomina-api';

export interface DatosAgregarEstudiante {
  periodo: Periodo;
  practicas: Practica[];
  areas: Area[];
}

// HU-07: carga individual de un estudiante (pantalla 07b). Primero se busca entre los ya registrados;
// si no está, se llena como estudiante nuevo. Las reglas las revisa el backend
@Component({
  selector: 'app-agregar-estudiante',
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatDialogModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
  ],
  templateUrl: './agregar-estudiante.html',
  styleUrl: './agregar-estudiante.scss',
})
export class AgregarEstudiante {
  private readonly api = inject(NominaApi);
  private readonly referencia = inject(MatDialogRef<AgregarEstudiante, boolean>);
  protected readonly datos = inject<DatosAgregarEstudiante>(MAT_DIALOG_DATA);

  protected readonly form = inject(NonNullableFormBuilder).group({
    nombre: ['', Validators.required],
    rut: ['', Validators.required],
    correo: ['', [Validators.required, Validators.email]],
    tipo_practica_id: [0, Validators.min(1)],
    anio_ingreso: [new Date().getFullYear(), Validators.required],
    cumple_prerrequisito: [true],
    area_ids: [[] as number[]],
  });
  protected readonly enviando = signal(false);
  protected readonly errorGeneral = signal<string | null>(null);

  protected readonly busqueda = signal('');
  protected readonly buscando = signal(false);
  protected readonly resultados = signal<EstudianteRegistrado[]>([]);
  protected readonly elegido = signal<EstudianteRegistrado | null>(null);
  private readonly nuevo = signal(false);
  protected readonly modo = computed(() => (this.elegido() ? 'registrado' : this.nuevo() ? 'nuevo' : 'buscar'));
  private readonly textos = new Subject<string>();

  constructor() {
    this.textos
      .pipe(
        debounceTime(250),
        // Un error en una búsqueda no corta las siguientes
        switchMap((texto) =>
          texto.length < 2
            ? of({ data: [] })
            : this.api.buscarEstudiantes(texto).pipe(
                catchError(() => {
                  this.errorGeneral.set('No se pudo buscar. Inténtalo de nuevo.');
                  return of({ data: [] });
                }),
              ),
        ),
        takeUntilDestroyed(inject(DestroyRef)),
      )
      .subscribe(({ data }) => {
        this.resultados.set(data);
        this.buscando.set(false);
      });
  }

  protected buscar(texto: string): void {
    this.busqueda.set(texto);
    this.buscando.set(true);
    this.errorGeneral.set(null);
    this.textos.next(texto.trim());
  }

  // Sus datos quedan fijos: solo se eligen la práctica, las áreas y el prerrequisito
  protected elegir(estudiante: EstudianteRegistrado): void {
    this.form.patchValue({
      nombre: estudiante.nombre,
      rut: estudiante.rut,
      correo: estudiante.correo,
      anio_ingreso: estudiante.anio_ingreso,
      area_ids: estudiante.area_ids,
    });
    this.form.controls.anio_ingreso.disable();
    this.elegido.set(estudiante);
  }

  protected empezarNuevo(): void {
    this.form.reset();
    this.form.controls.anio_ingreso.enable();
    this.nuevo.set(true);
  }

  protected volverABuscar(): void {
    this.form.reset();
    this.form.controls.anio_ingreso.enable();
    this.elegido.set(null);
    this.nuevo.set(false);
    this.errorGeneral.set(null);
  }

  protected guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    this.enviando.set(true);
    this.errorGeneral.set(null);

    this.api.agregar({ ...this.form.getRawValue(), periodo_id: this.datos.periodo.id }).subscribe({
      next: () => this.referencia.close(true),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        this.mostrarErrores(e);
      },
    });
  }

  // Los mensajes del backend llegan por campo ("Correo no institucional", "RUT inválido"...)
  private mostrarErrores(e: HttpErrorResponse): void {
    const errores = e.error?.errors as Record<string, string[]> | undefined;
    if (e.status !== 422 || !errores) {
      this.errorGeneral.set(e.status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado.');
      return;
    }
    for (const [campo, mensajes] of Object.entries(errores)) {
      const control = this.form.get(campo);
      if (control) {
        control.setErrors({ servidor: mensajes[0] });
        control.markAsTouched();
      } else {
        this.errorGeneral.set(mensajes[0]);
      }
    }
  }
}
