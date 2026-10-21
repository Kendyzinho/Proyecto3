import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';

import { mensajeDeError } from '../../secretaria/usuarios/usuarios-api';
import { FormularioPractica, ResultadoPractica } from './formulario-practica';
import { CATEGORIAS, Categoria, Malla, MallasApi, PracticaMalla } from './mallas-api';
import { NuevaMalla } from './nueva-malla';

const MENSAJES: Record<ResultadoPractica, string> = {
  agregada: 'Práctica agregada.',
  guardada: 'Cambios guardados.',
  quitada: 'Práctica quitada de la malla.',
};

// HU-06: el Jefe de Carrera configura las prácticas de cada malla y sus horas (pantalla 09)
@Component({
  selector: 'app-mallas',
  imports: [
    ReactiveFormsModule,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './mallas.html',
  styleUrl: './mallas.scss',
})
export class Mallas {
  private readonly api = inject(MallasApi);
  private readonly dialogo = inject(MatDialog);
  private readonly aviso = inject(MatSnackBar);

  protected readonly columnas = ['nombre', 'categoria', 'horas', 'prerrequisito', 'acciones'];
  protected readonly mallas = signal<Malla[]>([]);
  protected readonly cargando = signal(true);
  protected readonly hayEnPreparacion = computed(() => this.mallas().some((m) => m.estado === 'en_preparacion'));

  // Solo hay una malla en preparación a la vez, así que basta un campo de fecha
  protected readonly vigenteDesde = new FormControl('', { nonNullable: true, validators: Validators.required });
  protected readonly hoy = hoyIso();
  protected readonly publicando = signal(false);

  protected readonly fechaLegible = fechaLegible;

  constructor() {
    this.cargar();
  }

  protected textoCategoria(categoria: Categoria): string {
    return CATEGORIAS.find((c) => c.valor === categoria)?.texto ?? categoria;
  }

  protected nuevaMalla(): void {
    this.dialogo
      .open(NuevaMalla, { width: '440px' })
      .afterClosed()
      .subscribe((creada: boolean | undefined) => {
        if (creada) {
          this.aviso.open('Malla creada. Agrégale sus prácticas.', 'Cerrar', { duration: 5000 });
          this.cargar();
        }
      });
  }

  protected agregar(malla: Malla): void {
    this.abrirFormulario(malla, null);
  }

  protected editar(malla: Malla, practica: PracticaMalla): void {
    this.abrirFormulario(malla, practica);
  }

  protected publicar(malla: Malla): void {
    if (this.vigenteDesde.invalid) {
      this.vigenteDesde.markAsTouched();
      return;
    }
    this.publicando.set(true);
    this.api.publicar(malla.id, this.vigenteDesde.value).subscribe({
      next: (publicada) => {
        this.publicando.set(false);
        this.vigenteDesde.reset();
        this.aviso.open(`${publicada.nombre} rige desde el ${fechaLegible(publicada.vigente_desde!)}.`, 'Cerrar', { duration: 5000 });
        this.cargar();
      },
      error: (e: HttpErrorResponse) => {
        this.publicando.set(false);
        this.aviso.open(mensajeDeError(e), 'Cerrar', { duration: 5000 });
      },
    });
  }

  private abrirFormulario(malla: Malla, practica: PracticaMalla | null): void {
    this.dialogo
      .open(FormularioPractica, { data: { malla, practica }, width: '600px', autoFocus: 'first-tabbable' })
      .afterClosed()
      .subscribe((resultado: ResultadoPractica | undefined) => {
        if (resultado) {
          this.aviso.open(MENSAJES[resultado], 'Cerrar', { duration: 5000 });
          this.cargar();
        }
      });
  }

  private cargar(): void {
    this.api.listar().subscribe({
      next: (mallas) => {
        this.mallas.set(mallas);
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false),
    });
  }
}

// Fecha local de hoy en formato yyyy-mm-dd, para el mínimo del campo de fecha
function hoyIso(): string {
  const hoy = new Date();
  return `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
}

// 2027-03-01 → 01-03-2027
function fechaLegible(fecha: string): string {
  const [anio, mes, dia] = fecha.split('-');
  return `${dia}-${mes}-${anio}`;
}
