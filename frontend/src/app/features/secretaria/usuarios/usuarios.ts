import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, signal, viewChild } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginator, MatPaginatorModule } from '@angular/material/paginator';
import { MatSnackBar } from '@angular/material/snack-bar';
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { RouterLink } from '@angular/router';
import { Rol } from '../../../core/models/usuario';
import { Auth } from '../../../core/services/auth';
import { iniciales } from '../../../shared/marco/marco';
import { DesactivarUsuario } from './desactivar-usuario';
import { FormularioUsuario } from './formulario-usuario';
import { Cuenta, NOMBRE_ROL_CUENTA, UsuariosApi, mensajeDeError } from './usuarios-api';

type Filtro = 'todos' | 'estudiantes' | 'personal' | 'desactivados';

// HU-05: la Secretaria administra las cuentas de estudiantes, coordinación y jefatura (pantalla 05)
@Component({
  selector: 'app-usuarios',
  imports: [
    RouterLink,
    MatButtonModule,
    MatFormFieldModule,
    MatIconModule,
    MatInputModule,
    MatPaginatorModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './usuarios.html',
  styleUrl: './usuarios.scss',
})
export class Usuarios {
  private readonly api = inject(UsuariosApi);
  private readonly dialogo = inject(MatDialog);
  private readonly aviso = inject(MatSnackBar);
  private readonly auth = inject(Auth);

  protected readonly columnas = ['usuario', 'rut', 'rol', 'estado', 'acciones'];
  protected readonly filtros: { valor: Filtro; texto: string }[] = [
    { valor: 'todos', texto: 'Todos' },
    { valor: 'estudiantes', texto: 'Estudiantes' },
    { valor: 'personal', texto: 'Personal' },
    { valor: 'desactivados', texto: 'Desactivados' },
  ];

  protected readonly cuentas = signal<Cuenta[]>([]);
  protected readonly filtro = signal<Filtro>('todos');
  protected readonly busqueda = signal('');
  protected readonly cargando = signal(true);

  protected readonly filas = new MatTableDataSource<Cuenta>([]);
  private readonly paginador = viewChild(MatPaginator);

  protected readonly iniciales = iniciales;
  protected textoRol(rol: Rol): string {
    return NOMBRE_ROL_CUENTA[rol];
  }
  private readonly visibles = computed(() => {
    const texto = normalizar(this.busqueda());
    return this.cuentas().filter((c) => coincideFiltro(c, this.filtro()) && (texto === '' || coincideBusqueda(c, texto)));
  });

  constructor() {
    this.cargar();
    effect(() => {
      this.filas.data = this.visibles();
    });
    effect(() => {
      this.filas.paginator = this.paginador() ?? null;
    });
  }

  // La secretaria no puede desactivarse a sí misma
  protected esPropia(cuenta: Cuenta): boolean {
    return cuenta.id === this.auth.usuario()?.id;
  }

  protected nuevo(): void {
    this.abrirFormulario(null);
  }

  protected editar(cuenta: Cuenta): void {
    this.abrirFormulario(cuenta);
  }

  protected desactivar(cuenta: Cuenta): void {
    this.dialogo
      .open(DesactivarUsuario, { data: cuenta, width: '480px' })
      .afterClosed()
      .subscribe((hecho: boolean | undefined) => {
        if (hecho) {
          this.aviso.open(`Se desactivó la cuenta de ${cuenta.name}.`, 'Cerrar', { duration: 5000 });
          this.cargar();
        }
      });
  }

  protected reactivar(cuenta: Cuenta): void {
    this.api.reactivar(cuenta.id).subscribe({
      next: () => {
        this.aviso.open(`Se reactivó la cuenta de ${cuenta.name}.`, 'Cerrar', { duration: 5000 });
        this.cargar();
      },
      error: (e: HttpErrorResponse) => this.aviso.open(mensajeDeError(e), 'Cerrar', { duration: 5000 }),
    });
  }

  private abrirFormulario(cuenta: Cuenta | null): void {
    this.dialogo
      .open(FormularioUsuario, { data: cuenta, width: '600px', autoFocus: 'first-tabbable' })
      .afterClosed()
      .subscribe((guardado: boolean | undefined) => {
        if (guardado) {
          this.aviso.open(cuenta ? 'Cambios guardados.' : 'Usuario creado.', 'Cerrar', { duration: 5000 });
          this.cargar();
        }
      });
  }

  private cargar(): void {
    this.api.listar().subscribe({
      next: (cuentas) => {
        this.cuentas.set(cuentas);
        this.cargando.set(false);
      },
      error: () => this.cargando.set(false),
    });
  }
}

function coincideFiltro(cuenta: Cuenta, filtro: Filtro): boolean {
  switch (filtro) {
    case 'estudiantes':
      return cuenta.rol === 'estudiante';
    case 'personal':
      return cuenta.rol !== 'estudiante';
    case 'desactivados':
      return !cuenta.activo;
    default:
      return true;
  }
}

// "Matías" y "matias" coinciden; el RUT se compara sin puntos ni guion
function coincideBusqueda(cuenta: Cuenta, texto: string): boolean {
  const rutBuscado = texto.replace(/[.\-\s]/g, '');
  const rut = (cuenta.rut ?? '').replace(/[.-]/g, '').toLowerCase();
  return (
    normalizar(cuenta.name).includes(texto) ||
    cuenta.email.toLowerCase().includes(texto) ||
    (rutBuscado !== '' && rut.includes(rutBuscado))
  );
}

function normalizar(texto: string): string {
  return texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}
