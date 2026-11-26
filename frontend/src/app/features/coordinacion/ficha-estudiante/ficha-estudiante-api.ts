import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

import { FichaEstudianteDatos } from './ficha-estudiante.model';

@Injectable({ providedIn: 'root' })
export class FichaEstudianteApi {
  private readonly http = inject(HttpClient);

  consultar(practicaId: number) {
    return this.http.get<{ data: FichaEstudianteDatos }>(`/api/coordinacion/practicas/${practicaId}/ficha`);
  }
}
