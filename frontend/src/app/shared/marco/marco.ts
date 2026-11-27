import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { BreakpointObserver } from '@angular/cdk/layout';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatPaginatorIntl } from '@angular/material/paginator';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatDialog } from '@angular/material/dialog';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { PaginadorEnEspanol } from '../../core/i18n/paginador';
import { NOMBRE_ROL } from '../../core/models/usuario';
import { Auth } from '../../core/services/auth';
import { LUZ_ALERTA, fechaCorta, textoAlerta } from '../../core/models/alerta';
import { FichaEstudiante } from '../../features/coordinacion/ficha-estudiante/ficha-estudiante';
import { MenuUsuario } from '../menu-usuario/menu-usuario';
import { ContadorSolicitudes } from './contador-solicitudes';
import { GRUPOS_POR_ROL, MENUS_POR_ROL } from './menus-por-rol';
import { Notificacion, NotificacionesAlertas } from './notificaciones-alertas';

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
  protected readonly menuColapsado = signal(false);
  protected readonly notificacionesAbiertas = signal(false);

  protected readonly auth = inject(Auth);
  protected readonly contador = inject(ContadorSolicitudes);
  // HU-14: la campana muestra a cada rol las alertas que le tocan
  protected readonly notificaciones = inject(NotificacionesAlertas);
  protected readonly luces = LUZ_ALERTA;
  private readonly dialogo = inject(MatDialog);
  private readonly router = inject(Router);
  private readonly rol = computed(() => this.auth.usuario()?.rol);
  private readonly gestiona = computed(() => this.rol() === 'coordinadora' || this.rol() === 'jefe');

  protected readonly nombreRol = computed(() => {
    const usuario = this.auth.usuario();
    return usuario ? NOMBRE_ROL[usuario.rol] : '';
  });
  protected readonly grupoVisible = computed(() => {
    const rol = this.auth.usuario()?.rol;
    return this.perfil() && rol ? GRUPOS_POR_ROL[rol] ?? '' : this.grupo();
  });
  protected readonly menuVisible = computed<OpcionMenu[]>(() => {
    const rol = this.auth.usuario()?.rol;
    if (this.perfil()) return rol ? MENUS_POR_ROL[rol] ?? [] : [];

    // Usa el menú de la ruta si está definido; el menú por rol sirve como respaldo.
    return this.menu() ?? (rol ? MENUS_POR_ROL[rol] ?? [] : []);
  });

  constructor() {
    effect(() => {
      const rol = this.auth.usuario()?.rol;
      if (rol === 'coordinadora' || rol === 'jefe') this.contador.actualizar();
      if (rol) this.notificaciones.actualizar();
    });
  }

  protected alternarMenu(): void {
    if (this.movil()) {
      this.menuMovil.update((abierto) => !abierto);
      return;
    }

    this.menuColapsado.update((colapsado) => !colapsado);
  }

  // Abre o cierra el panel; al abrirlo vuelve a pedir las alertas por si salieron nuevas
  protected alternarNotificaciones(): void {
    this.notificacionesAbiertas.update((abiertas) => !abiertas);
    if (this.notificacionesAbiertas()) this.notificaciones.actualizar();
  }

  protected sinNotificaciones(): string {
    if (this.gestiona()) return 'No hay alertas abiertas.';
    if (this.rol() === 'supervisor') return 'No tienes semanas por confirmar.';
    if (this.rol() === 'estudiante') return 'Tu bitácora está al día.';
    return 'No tienes notificaciones.';
  }

  protected titulo(linea: Notificacion): string {
    if (this.rol() === 'estudiante') {
      return linea.tipo === 'abandono' ? 'Llevas 3 días sin marcar tu bitácora' : 'Tienes una falta sin justificar';
    }
    return linea.estudiante;
  }

  protected detalle(linea: Notificacion): string {
    const dia = fechaCorta(linea.fecha_referencia);
    if (this.rol() === 'estudiante') return linea.tipo === 'abandono' ? `Desde el ${dia}` : `Del ${dia}`;
    if (this.rol() === 'supervisor') return `Semana del ${dia} sin confirmar`;
    const texto = textoAlerta(linea.tipo, linea.fecha_referencia);
    if (linea.otras === 0) return texto;
    return `${texto}, y ${linea.otras} ${linea.otras === 1 ? 'alerta' : 'alertas'} más`;
  }

  // Coordinación abre la ficha del estudiante, el estudiante va a su bitácora
  // y el supervisor(a) a la página para confirmar la semana
  protected abrir(linea: Notificacion): void {
    this.notificacionesAbiertas.set(false);
    if (this.gestiona()) {
      this.dialogo
        .open(FichaEstudiante, { data: linea.practica_id, width: '820px', maxWidth: 'calc(100vw - 24px)', maxHeight: '94dvh' })
        .afterClosed()
        .subscribe(() => this.notificaciones.actualizar());
    } else if (this.rol() === 'estudiante') {
      this.router.navigateByUrl('/estudiante/bitacora');
    } else if (linea.enlace) {
      this.router.navigateByUrl(linea.enlace.replace(/^https?:\/\/[^/]+/, ''));
    }
  }
}

export function iniciales(nombre: string): string {
  return nombre
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0].toUpperCase())
    .join('');
}