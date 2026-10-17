import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { RouterLink } from '@angular/router';

import { DocumentosApi } from '../documentos/documentos-api';
import { NominaApi } from '../nomina/nomina-api';

// Inicio de la Secretaria de Jefatura (pantalla 00). La tarjeta de Usuarios (HU-05) se agrega cuando exista esa pantalla.
@Component({
  selector: 'app-inicio-secretaria',
  imports: [MatButtonModule, RouterLink],
  templateUrl: './inicio.html',
  styleUrl: './inicio.scss',
})
export class Inicio {
  private readonly api = inject(NominaApi);
  private readonly documentos = inject(DocumentosApi);

  protected readonly periodo = signal('');
  protected readonly habilitados = signal<number | null>(null);
  protected readonly porFormalizar = signal<number | null>(null);

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
      // Prácticas a las que aún les falta enviar la carta o la guía del seguro
      this.documentos
        .listar(actual.id)
        .subscribe(({ data }) => this.porFormalizar.set(data.filter((p) => p.carta.estado !== 'enviada' || p.seguro.estado !== 'enviada').length));
    });
  }
}
