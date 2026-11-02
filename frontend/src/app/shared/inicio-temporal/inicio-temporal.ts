import { Component, computed, inject } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

import { Auth } from '../../core/services/auth';
import { MENUS_POR_ROL } from '../marco/menus-por-rol';

// Inicio de los roles que aún no tienen uno propio: accesos a las opciones de su menú
@Component({
  selector: 'app-inicio-temporal',
  imports: [MatIconModule, RouterLink],
  templateUrl: './inicio-temporal.html',
  styleUrl: './inicio-temporal.scss',
})
export class InicioTemporal {
  private readonly auth = inject(Auth);
  protected readonly accesos = computed(() => {
    const rol = this.auth.usuario()?.rol;
    return (rol ? MENUS_POR_ROL[rol] ?? [] : []).slice(1);
  });
}
