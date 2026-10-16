import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';

import { Auth } from '../../core/services/auth';

// Pantalla provisoria para probar el login y el cierre de sesión mientras no exista el inicio de cada rol.
@Component({
  selector: 'app-inicio-temporal',
  imports: [MatButtonModule],
  templateUrl: './inicio-temporal.html',
  styleUrl: './inicio-temporal.scss',
})
export class InicioTemporal {
  protected readonly auth = inject(Auth);
}
