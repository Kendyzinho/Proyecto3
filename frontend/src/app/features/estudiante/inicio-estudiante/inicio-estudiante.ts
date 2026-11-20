import { Component } from '@angular/core';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

import { MENUS_POR_ROL } from '../../../shared/marco/menus-por-rol';

@Component({
  selector: 'app-inicio-estudiante',
  imports: [MatIconModule, RouterLink],
  templateUrl: './inicio-estudiante.html',
  styleUrl: './inicio-estudiante.scss',
})
export class InicioEstudiante {
  protected readonly accesos = MENUS_POR_ROL.estudiante.filter((opcion) => opcion.ruta !== '/estudiante');
}
