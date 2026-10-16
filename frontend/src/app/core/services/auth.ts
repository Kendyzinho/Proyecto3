import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';

import { Rol, Usuario } from '../models/usuario';

interface RespuestaLogin {
  token: string;
  usuario: Usuario;
}

const CLAVE_TOKEN = 'token';
const CLAVE_USUARIO = 'usuario';

// Pantalla de inicio de cada rol después de iniciar sesión (HU-01)
export const INICIO_POR_ROL: Record<Rol, string> = {
  secretaria: '/secretaria',
  coordinadora: '/coordinacion',
  jefe: '/jefatura',
  estudiante: '/estudiante',
};

@Injectable({
  providedIn: 'root',
})
export class Auth {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);
  private readonly usuarioActual = signal<Usuario | null>(leerUsuarioGuardado());

  readonly usuario = this.usuarioActual.asReadonly();
  readonly autenticado = computed(() => this.usuarioActual() !== null);

  get token(): string | null {
    return localStorage.getItem(CLAVE_TOKEN);
  }

  login(email: string, password: string): Observable<RespuestaLogin> {
    return this.http.post<RespuestaLogin>('/api/login', { email, password }).pipe(
      tap(({ token, usuario }) => {
        localStorage.setItem(CLAVE_TOKEN, token);
        localStorage.setItem(CLAVE_USUARIO, JSON.stringify(usuario));
        this.usuarioActual.set(usuario);
      }),
    );
  }

  // HU-03: invalida el token en el servidor y vuelve al inicio de sesión
  logout(): void {
    if (this.token) {
      this.http.post('/api/logout', {}).subscribe({ error: () => {} });
    }
    this.limpiarSesion();
    this.router.navigateByUrl('/auth');
  }

  limpiarSesion(): void {
    localStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_USUARIO);
    this.usuarioActual.set(null);
  }

  inicio(): string {
    const usuario = this.usuarioActual();
    return usuario ? INICIO_POR_ROL[usuario.rol] : '/auth';
  }
}

function leerUsuarioGuardado(): Usuario | null {
  const guardado = localStorage.getItem(CLAVE_USUARIO);
  return guardado ? (JSON.parse(guardado) as Usuario) : null;
}
