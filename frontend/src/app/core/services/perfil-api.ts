import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { Usuario } from '../models/usuario';

export interface CambioContrasena {
  current_password: string;
  password: string;
  password_confirmation: string;
}

@Injectable({ providedIn: 'root' })
export class PerfilApi {
  private readonly http = inject(HttpClient);

  consultar(): Observable<Usuario | null> {
    return this.http.get<{ data: Usuario | null }>('/api/me').pipe(map(({ data }) => data));
  }

  cambiarContrasena(datos: CambioContrasena): Observable<void> {
    return this.http.put<void>('/api/me/password', datos);
  }
}
