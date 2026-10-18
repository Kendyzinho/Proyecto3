import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';

import { Auth } from '../../core/services/auth';

interface OpcionMenu {
  etiqueta: string;
  icono: string;
  ruta?: string;
  badge?: number;
}

@Component({
  selector: 'app-aplicacion-shell',
  imports: [RouterLink, RouterLinkActive, MatIconModule],
  templateUrl: './aplicacion-shell.html',
  styleUrl: './aplicacion-shell.scss',
})
export class AplicacionShell {
  protected readonly auth = inject(Auth);
  protected readonly menuColapsado = signal(false);
  protected readonly menuMovilAbierto = signal(false);
  protected readonly mostrarNotificaciones = signal(false);
  protected readonly mostrarPerfil = signal(false);
  protected readonly usuario = this.auth.usuario;
  protected readonly iniciales = computed(() => (this.usuario()?.name ?? 'U').split(/\s+/).filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toLocaleUpperCase('es'));
  protected readonly menu = computed<OpcionMenu[]>(() => {
    const rol = this.usuario()?.rol;
    if (rol === 'coordinadora') return [
      { etiqueta: 'Inicio', icono: 'home', ruta: '/coordinacion' },
      { etiqueta: 'Prácticas', icono: 'work', ruta: '/coordinacion/asignaciones' },
      { etiqueta: 'Solicitudes', icono: 'inbox', badge: 3 },
      { etiqueta: 'Centros de práctica', icono: 'domain' },
      { etiqueta: 'Visitas técnicas', icono: 'event' },
      { etiqueta: 'Dictámenes', icono: 'fact_check' },
      { etiqueta: 'Estadísticas', icono: 'bar_chart' },
    ];
    if (rol === 'estudiante') return [
      { etiqueta: 'Inicio', icono: 'home', ruta: '/estudiante' },
      { etiqueta: 'Mi asistencia', icono: 'schedule', ruta: '/estudiante/asistencia' },
      { etiqueta: 'Solicitud de práctica', icono: 'send', ruta: '/estudiante/solicitud' },
      { etiqueta: 'Evaluación del centro', icono: 'assignment', ruta: '/estudiante/evaluacion' },
      { etiqueta: 'Portafolio', icono: 'link', ruta: '/estudiante/portafolio' },
    ];
    const etiqueta = rol === 'secretaria' ? 'Secretaría de Jefatura' : 'Jefatura de Carrera';
    return [{ etiqueta: 'Inicio', icono: 'home', ruta: rol === 'secretaria' ? '/secretaria' : '/jefatura' }, { etiqueta, icono: 'dashboard' }];
  });
  protected readonly nombreRol = computed(() => ({ coordinadora: 'Coordinadora de Prácticas', estudiante: 'Estudiante Practicante', secretaria: 'Secretaria de Jefatura', jefe: 'Jefatura de Carrera' })[this.usuario()?.rol ?? 'estudiante']);

  protected alternarMenu(): void {
    if (globalThis.matchMedia('(max-width: 760px)').matches) this.menuMovilAbierto.update((abierto) => !abierto);
    else this.menuColapsado.update((colapsado) => !colapsado);
  }

  protected cerrarMenus(): void {
    this.mostrarNotificaciones.set(false);
    this.mostrarPerfil.set(false);
    this.menuMovilAbierto.set(false);
  }
}
