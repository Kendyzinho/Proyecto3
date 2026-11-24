import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';

export interface PracticaPortafolio { id: number; tipo: string; centro: string | null; url: string | null }
@Injectable({ providedIn: 'root' })
export class PortafolioApi {
  private readonly http = inject(HttpClient);
  listar() { return this.http.get<{ data: PracticaPortafolio[] }>('/api/estudiante/portafolios'); }
  guardar(id: number, url: string) { return this.http.put<{ data: { url: string } }>(`/api/estudiante/practicas/${id}/portafolio`, { url }); }
}
