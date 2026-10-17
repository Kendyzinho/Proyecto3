import { Component, computed, inject, input, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatPaginatorIntl } from '@angular/material/paginator';
import { MatSidenavModule } from '@angular/material/sidenav';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { PaginadorEnEspanol } from '../../core/i18n/paginador';
import { NOMBRE_ROL } from '../../core/models/usuario';
import { Auth } from '../../core/services/auth';

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
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatSidenavModule, MatButtonModule, MatIconModule, MatMenuModule],
  templateUrl: './marco.html',
  styleUrl: './marco.scss',
  // Paginador en español para todas las tablas dentro del marco
  providers: [{ provide: MatPaginatorIntl, useClass: PaginadorEnEspanol }],
})
export class Marco {
  readonly grupo = input.required<string>();
  readonly menu = input.required<OpcionMenu[]>();

  protected readonly auth = inject(Auth);
  protected readonly menuAbierto = signal(true);
  protected readonly nombreRol = computed(() => {
    const usuario = this.auth.usuario();
    return usuario ? NOMBRE_ROL[usuario.rol] : '';
  });
  protected readonly iniciales = computed(() => iniciales(this.auth.usuario()?.name ?? ''));
}

export function iniciales(nombre: string): string {
  return nombre
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0].toUpperCase())
    .join('');
}
