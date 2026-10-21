import { Component, computed, inject, input, signal } from '@angular/core';
import { BreakpointObserver } from '@angular/cdk/layout';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatPaginatorIntl } from '@angular/material/paginator';
import { MatSidenavModule } from '@angular/material/sidenav';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { PaginadorEnEspanol } from '../../core/i18n/paginador';
import { NOMBRE_ROL } from '../../core/models/usuario';
import { Auth } from '../../core/services/auth';
import { MenuUsuario } from '../menu-usuario/menu-usuario';

export interface OpcionMenu {
  texto: string;
  icono: string;
  ruta: string;
}

// Marco común de las pantallas con sesión: menú lateral y barra superior.
// Cada rol lo usa en sus rutas y le pasa su grupo y sus opciones en `data`:
// { path: '', component: Marco, data: { grupo: 'Secretaría de Jefatura', menu: [...] }, children: [...] }
@Component({
  selector: 'app-marco',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatSidenavModule, MatButtonModule, MatIconModule, MenuUsuario],
  templateUrl: './marco.html',
  styleUrl: './marco.scss',
  // Paginador en español para todas las tablas dentro del marco
  providers: [{ provide: MatPaginatorIntl, useClass: PaginadorEnEspanol }],
})
export class Marco {
  readonly grupo = input('');
  readonly menu = input<OpcionMenu[] | null>(null);
  readonly perfil = input(false);
  readonly adaptable = input(false);
  private readonly pantalla = toSignal(inject(BreakpointObserver).observe('(max-width: 700px)'));
  protected readonly movil = computed(() => this.adaptable() && !!this.pantalla()?.matches);
  protected readonly menuMovil = signal(false);

  protected readonly auth = inject(Auth);
  protected readonly menuAbierto = signal(true);
  protected readonly nombreRol = computed(() => {
    const usuario = this.auth.usuario();
    return usuario ? NOMBRE_ROL[usuario.rol] : '';
  });
  protected readonly grupoVisible = computed(() => this.perfil() ? `Área personal · ${this.nombreRol()}` : this.grupo());
  protected readonly menuVisible = computed<OpcionMenu[]>(() => {
    if (!this.perfil()) return this.menu() ?? [];
    const inicio = this.auth.inicio();
    const opciones: OpcionMenu[] = [{ texto: 'Inicio', icono: 'home', ruta: inicio }];
    if (this.auth.usuario()?.rol === 'secretaria') {
      opciones.push(
        { texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' },
        { texto: 'Documentos', icono: 'description', ruta: '/secretaria/documentos' },
      );
    }
    return opciones;
  });
}

export function iniciales(nombre: string): string {
  return nombre
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0].toUpperCase())
    .join('');
}
