import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';

import { NominaApi } from '../nomina/nomina-api';

// Inicio de la Secretaria de Jefatura (pantalla 00). Las tarjetas de Usuarios (HU-05)
// y Documentos (HU-10) se agregan cuando existan esas pantallas.
@Component({
  selector: 'app-inicio-secretaria',
  imports: [MatButtonModule, RouterLink],
  templateUrl: './inicio.html',
  styleUrl: './inicio.scss',
})
export class Inicio {
  private readonly api = inject(NominaApi);

  protected readonly periodo = signal('');
  protected readonly habilitados = signal<number | null>(null);

  constructor() {
    this.api.catalogo().subscribe((catalogo) => {
      const actual = catalogo.periodos.find((p) => p.id === catalogo.periodo_actual_id);
      if (!actual) {
        return;
      }
      this.periodo.set(actual.nombre);
      this.api
        .listar(actual.id)
        .subscribe(({ data }) => this.habilitados.set(data.filter((e) => e.resultado === 'habilitado').length));
    });
  }
}
