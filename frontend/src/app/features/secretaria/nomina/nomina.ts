import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, signal, viewChild } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSelectModule } from '@angular/material/select';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';

import { iniciales } from '../../../shared/marco/marco';
import { AgregarEstudiante } from './agregar-estudiante';
import { Catalogo, EstudianteNomina, FilaRevisada, NominaApi, Revision, descargar, textoPrerrequisito } from './nomina-api';

// Fila de la tabla: sale de la revisión de una planilla o de la nómina ya guardada
type FilaTabla = Pick<FilaRevisada, 'rut' | 'nombre' | 'correo' | 'practica' | 'prerrequisito' | 'cumple' | 'resultado'> & {
  errores?: string;
};

const ENCABEZADOS_CSV = ['RUT', 'Nombre', 'Correo', 'Año de ingreso', 'Práctica', 'Cumple prerrequisito', 'Error'];

// HU-07: la Secretaria carga la nómina del período desde una planilla y revisa quién cumple el prerrequisito
@Component({
  selector: 'app-nomina',
  imports: [
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatPaginatorModule,
    MatSelectModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './nomina.html',
  styleUrl: './nomina.scss',
})
export class Nomina {
  private readonly api = inject(NominaApi);
  private readonly dialogo = inject(MatDialog);
  private readonly aviso = inject(MatSnackBar);

  protected readonly columnas = ['rut', 'estudiante', 'practica', 'prerrequisito', 'resultado'];
  protected readonly catalogo = signal<Catalogo | null>(null);
  protected readonly periodoId = signal<number | null>(null);
  protected readonly archivo = signal<File | null>(null);
  protected readonly revision = signal<Revision | null>(null);
  protected readonly nomina = signal<EstudianteNomina[]>([]);
  protected readonly ocupado = signal(false);
  protected readonly errorArchivo = signal<string | null>(null);

  protected readonly filas = new MatTableDataSource<FilaTabla>([]);
  private readonly paginador = viewChild(MatPaginator);

  protected readonly periodo = computed(() => this.catalogo()?.periodos.find((p) => p.id === this.periodoId()) ?? null);
  protected readonly porGuardar = computed(() => {
    const resumen = this.revision()?.resumen;
    return resumen ? resumen.habilitados + resumen.bloqueados : 0;
  });

  protected readonly iniciales = iniciales;
  protected readonly textoPrerrequisito = textoPrerrequisito;

  constructor() {
    this.api.catalogo().subscribe((catalogo) => {
      this.catalogo.set(catalogo);
      this.periodoId.set(catalogo.periodo_actual_id);
      this.cargarNomina();
    });

    // La tabla muestra la revisión mientras hay una planilla subida; si no, la nómina guardada
    effect(() => {
      const revision = this.revision();
      this.filas.data = revision ? revision.filas.map(filaDeRevision) : this.nomina();
    });
    effect(() => {
      this.filas.paginator = this.paginador() ?? null;
    });
  }

  protected cambiarPeriodo(id: number): void {
    this.periodoId.set(id);
    const archivo = this.archivo();
    if (archivo) {
      this.revisar(archivo);
    } else {
      this.cargarNomina();
    }
  }

  protected elegirArchivo(evento: Event): void {
    const entrada = evento.target as HTMLInputElement;
    const archivo = entrada.files?.[0];
    entrada.value = ''; // permite volver a elegir el mismo archivo
    if (archivo) {
      this.revisar(archivo);
    }
  }

  protected soltarArchivo(evento: DragEvent): void {
    evento.preventDefault();
    const archivo = evento.dataTransfer?.files[0];
    if (archivo) {
      this.revisar(archivo);
    }
  }

  // Excel de la nómina guardada, con las columnas de la plantilla
  protected descargarNomina(): void {
    const periodo = this.periodo();
    if (periodo) {
      const nombre = periodo.nombre.normalize('NFD').replace(/[^\w\s]/g, '').trim().replace(/\s+/g, '_').toLowerCase();
      this.api.exportar(periodo.id).subscribe((contenido) => descargar(contenido, `nomina_${nombre}.xlsx`));
    }
  }

  protected descargarPlantilla(): void {
    this.api.plantilla().subscribe((contenido) => descargar(contenido, 'plantilla_nomina.xlsx'));
  }

  // Planilla con solo las filas que hay que corregir y el motivo de cada una
  protected descargarErrores(): void {
    const conError = (this.revision()?.filas ?? []).filter((f) => f.resultado === 'error');
    const lineas = conError.map((f) =>
      [f.rut, f.nombre, f.correo, f.anio_ingreso, f.practica, f.cumple === null ? '' : f.cumple ? 'Sí' : 'No', motivos(f)]
        .map(celdaCsv)
        .join(';'),
    );
    // El BOM y el punto y coma hacen que Excel en español lo abra bien
    const csv = '﻿' + [ENCABEZADOS_CSV.join(';'), ...lineas].join('\r\n');
    descargar(new Blob([csv], { type: 'text/csv;charset=utf-8' }), 'filas_con_error.csv');
  }

  protected confirmar(): void {
    const archivo = this.archivo();
    const periodo = this.periodo();
    if (!archivo || !periodo) {
      return;
    }
    this.ocupado.set(true);
    this.api.confirmar(archivo, periodo.id).subscribe({
      next: (resultado) => {
        const cargados = resultado.habilitados + resultado.bloqueados;
        this.aviso.open(`Se cargaron ${cargados} estudiantes al ${periodo.nombre}.`, 'Cerrar', { duration: 5000 });
        this.quitarArchivo();
        this.cargarNomina();
      },
      error: (e: HttpErrorResponse) => {
        this.ocupado.set(false);
        this.errorArchivo.set(mensajeDeError(e));
      },
    });
  }

  protected agregarEstudiante(): void {
    const catalogo = this.catalogo();
    const periodo = this.periodo();
    if (!catalogo || !periodo) {
      return;
    }
    this.dialogo
      .open(AgregarEstudiante, { data: { periodo, practicas: catalogo.practicas }, width: '600px', autoFocus: 'first-tabbable' })
      .afterClosed()
      .subscribe((agregado: boolean | undefined) => {
        if (agregado) {
          this.aviso.open('Estudiante agregado a la nómina.', 'Cerrar', { duration: 5000 });
          if (!this.archivo()) {
            this.cargarNomina();
          }
        }
      });
  }

  protected quitarArchivo(): void {
    this.archivo.set(null);
    this.revision.set(null);
    this.errorArchivo.set(null);
    this.ocupado.set(false);
  }

  private revisar(archivo: File): void {
    const periodoId = this.periodoId();
    if (periodoId === null) {
      return;
    }
    this.archivo.set(archivo);
    this.revision.set(null);
    this.errorArchivo.set(null);
    this.ocupado.set(true);
    this.api.revisar(archivo, periodoId).subscribe({
      next: (revision) => {
        this.ocupado.set(false);
        this.revision.set(revision);
      },
      error: (e: HttpErrorResponse) => {
        this.ocupado.set(false);
        this.errorArchivo.set(mensajeDeError(e));
      },
    });
  }

  private cargarNomina(): void {
    const periodoId = this.periodoId();
    if (periodoId !== null) {
      this.api.listar(periodoId).subscribe(({ data }) => this.nomina.set(data));
    }
  }
}

function filaDeRevision(fila: FilaRevisada): FilaTabla {
  return { ...fila, errores: motivos(fila) };
}

// ["RUT inválido", "Correo no institucional"] -> "RUT inválido y correo no institucional"
function motivos(fila: FilaRevisada): string {
  const lista = Object.values(fila.errores).map((m, i) => (i === 0 || m.startsWith('RUT') ? m : m[0].toLowerCase() + m.slice(1)));
  return lista.length > 1 ? `${lista.slice(0, -1).join(', ')} y ${lista[lista.length - 1]}` : (lista[0] ?? '');
}

function celdaCsv(valor: unknown): string {
  const texto = String(valor ?? '');
  return /[;"\n]/.test(texto) ? `"${texto.replace(/"/g, '""')}"` : texto;
}

function mensajeDeError(e: HttpErrorResponse): string {
  const errores = e.error?.errors as Record<string, string[]> | undefined;
  if (e.status === 422 && errores) {
    return Object.values(errores)[0][0];
  }
  return e.status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado. Inténtalo de nuevo.';
}
