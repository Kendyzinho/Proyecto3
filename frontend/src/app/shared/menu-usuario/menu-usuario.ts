import { Component, computed, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { RouterLink, RouterLinkActive } from '@angular/router';

import { Auth } from '../../core/services/auth';

@Component({
  selector: 'app-menu-usuario',
  imports: [MatButtonModule, MatIconModule, MatMenuModule, RouterLink, RouterLinkActive],
  templateUrl: './menu-usuario.html',
  styleUrl: './menu-usuario.scss',
})
export class MenuUsuario {
  protected readonly auth = inject(Auth);
  protected readonly iniciales = computed(() => this.auth.usuario()?.name.trim().split(/\s+/)
    .slice(0, 2).map((parte) => parte.charAt(0)).join('').toUpperCase() ?? '');
}
