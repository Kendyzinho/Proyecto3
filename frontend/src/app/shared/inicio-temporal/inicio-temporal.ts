import { Component, inject } from '@angular/core';

import { Auth } from '../../core/services/auth';

// Pantalla provisoria mientras no exista el inicio de cada rol. La barra y el menú del usuario los pone el marco.
@Component({
  selector: 'app-inicio-temporal',
  templateUrl: './inicio-temporal.html',
  styleUrl: './inicio-temporal.scss',
})
export class InicioTemporal {
  protected readonly auth = inject(Auth);
}
