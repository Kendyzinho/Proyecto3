import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';

import { GuardarJornada, InformarInasistencia, PracticaBitacora, RegistroDiario } from './bitacora.model';

@Injectable({ providedIn: 'root' })
export class BitacoraApi {
  private readonly http = inject(HttpClient);

  listar() {
    return this.http.get<{ data: PracticaBitacora[] }>('/api/estudiante/bitacora');
  }

  detalle(practicaId: number) {
    return this.http.get<{ data: PracticaBitacora & { registros: RegistroDiario[] } }>(`/api/estudiante/practicas/${practicaId}/bitacora`);
  }

  guardar(practicaId: number, fecha: string, datos: GuardarJornada | InformarInasistencia) {
    return this.http.put<{ data: RegistroDiario }>(`/api/estudiante/practicas/${practicaId}/bitacora/${fecha}`, datos);
  }
}
