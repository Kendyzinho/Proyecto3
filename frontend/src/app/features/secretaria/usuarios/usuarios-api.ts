import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { Rol } from '../../../core/models/usuario';

// Una cuenta tal como la entrega GET /api/usuarios
export interface Cuenta {
  id: number;
  rut: string | null;
  name: string;
  email: string;
  rol: Rol;
  anio_ingreso: number | null;
  activo: boolean;
  fecha_baja: string | null;
  motivo_baja: string | null;
}

export interface DatosCuenta {
  rol: Rol;
  name: string;
  rut: string | null;
  email: string;
  anio_ingreso: number | null;
}

// Como se muestra el rol en la tabla y en el formulario (pantalla 05)
export const ROLES: { valor: Rol; texto: string }[] = [
  { valor: 'estudiante', texto: 'Estudiante' },
  { valor: 'secretaria', texto: 'Secretaria de Jefatura' },
  { valor: 'coordinadora', texto: 'Coordinadora de Prácticas' },
  { valor: 'jefe', texto: 'Jefe de Carrera' },
];

export const NOMBRE_ROL_CUENTA = Object.fromEntries(ROLES.map((r) => [r.valor, r.texto])) as Record<Rol, string>;

// HU-05: cuentas de usuario (solo Secretaria de Jefatura)
@Injectable({ providedIn: 'root' })
export class UsuariosApi {
  private readonly http = inject(HttpClient);

  listar(): Observable<Cuenta[]> {
    return this.http.get<{ data: Cuenta[] }>('/api/usuarios').pipe(map(({ data }) => data));
  }

  crear(datos: DatosCuenta): Observable<Cuenta> {
    return this.http.post<{ data: Cuenta }>('/api/usuarios', datos).pipe(map(({ data }) => data));
  }

  editar(id: number, datos: DatosCuenta): Observable<Cuenta> {
    return this.http.put<{ data: Cuenta }>(`/api/usuarios/${id}`, datos).pipe(map(({ data }) => data));
  }

  desactivar(id: number, motivo: string): Observable<Cuenta> {
    return this.http.post<{ data: Cuenta }>(`/api/usuarios/${id}/desactivar`, { motivo }).pipe(map(({ data }) => data));
  }

  reactivar(id: number): Observable<Cuenta> {
    return this.http.post<{ data: Cuenta }>(`/api/usuarios/${id}/reactivar`, {}).pipe(map(({ data }) => data));
  }
}

export function mensajeDeError(e: HttpErrorResponse): string {
  const errores = e.error?.errors as Record<string, string[]> | undefined;
  if (e.status === 422 && errores) {
    return Object.values(errores)[0][0];
  }
  return e.status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado. Inténtalo de nuevo.';
}
