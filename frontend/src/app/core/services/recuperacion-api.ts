import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export interface NuevaContrasena {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

// HU-02: recuperación de contraseña sin sesión iniciada
@Injectable({ providedIn: 'root' })
export class RecuperacionApi {
  private readonly http = inject(HttpClient);

  solicitarEnlace(email: string): Observable<void> {
    return this.http.post<void>('/api/recuperar-contrasena', { email });
  }

  restablecer(datos: NuevaContrasena): Observable<void> {
    return this.http.post<void>('/api/restablecer-contrasena', datos);
  }
}
