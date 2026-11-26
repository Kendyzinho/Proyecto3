import { Component, computed, effect, inject, signal, viewChild } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSelectModule } from '@angular/material/select';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';

import { descargar } from '../../secretaria/nomina/nomina-api';
import {
  EstadoOperativo,
  FILTROS_VACIOS,
  FiltrosListado,
  ListadoPracticas as ListadoPracticasResponse,
  ListadoPracticasApi,
  PracticaListada,
  filtrarPracticas,
} from './listado-practicas-api';

@Component({
  selector: 'app-listado-practicas',
  imports: [
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatPaginatorModule,
    MatSelectModule,
    MatTableModule,
  ],
  templateUrl: './listado-practicas.html',
  styleUrl: './listado-practicas.scss',
})
export class ListadoPracticas {
  private readonly api = inject(ListadoPracticasApi);

  protected readonly columnas = [
    'estudiante',
    'practica',
    'centro',
    'supervisor',
    'estado',
    'fechas',
  ];
  protected readonly listado = signal<ListadoPracticasResponse | null>(null);
  protected readonly busqueda = signal('');
  protected readonly tipoId = signal<number | null>(null);
  protected readonly estado = signal<EstadoOperativo | null>(null);
  protected readonly cargando = signal(true);
  protected readonly exportando = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly filas = new MatTableDataSource<PracticaListada>([]);
  private readonly paginador = viewChild(MatPaginator);

  protected readonly total = computed(() => this.listado()?.data.length ?? 0);
  protected readonly periodo = computed(() => this.listado()?.periodo ?? null);
  protected readonly tipos = computed(() => this.listado()?.catalogo.tipos ?? []);
  protected readonly estados = computed(() => this.listado()?.catalogo.estados ?? []);
  protected readonly filtros = computed<FiltrosListado>(() => ({
    q: this.busqueda(),
    tipo_practica_id: this.tipoId(),
    estado: this.estado(),
  }));
  protected readonly filasVisibles = computed(() =>
    filtrarPracticas(this.listado()?.data ?? [], this.filtros()),
  );
  protected readonly sinFiltros = computed(
    () => !this.busqueda().trim() && this.tipoId() === null && this.estado() === null,
  );

  constructor() {
    this.api.listar().subscribe({
      next: (listado) => {
        this.listado.set(listado);
        this.cargando.set(false);
      },
      error: () => {
        this.cargando.set(false);
        this.error.set('No se pudo cargar la lista general de prácticas.');
      },
    });

    effect(() => {
      this.filas.data = this.filasVisibles();
    });
    effect(() => {
      this.filas.paginator = this.paginador() ?? null;
    });
  }

  protected cambiarBusqueda(evento: Event): void {
    this.busqueda.set((evento.target as HTMLInputElement).value);
  }

  protected limpiarFiltros(): void {
    this.busqueda.set(FILTROS_VACIOS.q);
    this.tipoId.set(FILTROS_VACIOS.tipo_practica_id);
    this.estado.set(FILTROS_VACIOS.estado);
  }

  protected recargar(): void {
    this.error.set(null);
    this.cargando.set(true);
    this.api.listar().subscribe({
      next: (listado) => {
        this.listado.set(listado);
        this.cargando.set(false);
      },
      error: () => {
        this.cargando.set(false);
        this.error.set('No se pudo cargar la lista general de prácticas.');
      },
    });
  }

  protected exportar(): void {
    if (!this.periodo() || this.filasVisibles().length === 0) {
      return;
    }
    this.exportando.set(true);
    this.api.exportar(this.filtros()).subscribe({
      next: (contenido) => {
        this.exportando.set(false);
        descargar(contenido, this.nombreArchivo());
      },
      error: () => {
        this.exportando.set(false);
        this.error.set('No se pudo exportar el listado.');
      },
    });
  }

  protected textoFechas(fila: PracticaListada): string {
    if (!fila.fecha_inicio || !fila.fecha_fin) {
      return '—';
    }
    return `${this.fecha(fila.fecha_inicio)} a ${this.fecha(fila.fecha_fin)}`;
  }

  protected claseEstado(estado: string): string {
    switch (estado) {
      case 'en_curso':
        return 'estado--exito';
      case 'posible_abandono':
        return 'estado--error';
      case 'por_iniciar':
        return 'estado--info';
      default:
        return 'estado--neutro';
    }
  }

  private fecha(valor: string): string {
    return valor.split('-').reverse().join('-');
  }

  private nombreArchivo(): string {
    const nombre = this.periodo()?.nombre ?? 'practicas';
    const slug = nombre
      .normalize('NFD')
      .replace(/[^\w\s]/g, '')
      .trim()
      .replace(/\s+/g, '_')
      .toLowerCase();
    return `practicas_${slug}.xlsx`;
  }
}
