import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, Subject, takeUntil, tap, timeout } from 'rxjs';

import { Rol, Usuario } from '../models/usuario';

interface RespuestaLogin {
  token: string;
  usuario: Usuario;
}

const CLAVE_TOKEN = 'token';
const CLAVE_USUARIO = 'usuario';
const ESPERA_REVOCACION_MS = 10_000;
type EstadoCierre = 'ninguno' | 'pendiente' | 'revocado' | 'invalido' | 'solo_local';

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
  private readonly cierreActual = signal<EstadoCierre>('ninguno');
  private readonly finDeSesion = new Subject<void>();
  private revisionSesion = 0;

  readonly usuario = this.usuarioActual.asReadonly();
  readonly autenticado = computed(() => this.usuarioActual() !== null);
  readonly estadoCierre = this.cierreActual.asReadonly();
  readonly sesionFinalizada$ = this.finDeSesion.asObservable();

  constructor() {
    const sincronizarCierre = (evento: StorageEvent) => {
      if (evento.storageArea === localStorage && (evento.key === CLAVE_TOKEN || evento.key === null) && !this.token) {
        this.limpiarSesion();
        this.cierreActual.set('ninguno');
        void this.router.navigateByUrl('/auth', { replaceUrl: true });
      }
    };
    window.addEventListener('storage', sincronizarCierre);
    inject(DestroyRef).onDestroy(() => window.removeEventListener('storage', sincronizarCierre));
  }

  get token(): string | null {
    return localStorage.getItem(CLAVE_TOKEN);
  }

  login(email: string, password: string): Observable<RespuestaLogin> {
    return this.http.post<RespuestaLogin>('/api/login', { email, password }).pipe(
      tap(({ token, usuario }) => {
        this.revisionSesion++;
        this.cierreActual.set('ninguno');
        localStorage.setItem(CLAVE_TOKEN, token);
        localStorage.setItem(CLAVE_USUARIO, JSON.stringify(usuario));
        this.usuarioActual.set(usuario);
      }),
      takeUntil(this.sesionFinalizada$),
    );
  }

  logout(): void {
    const token = this.token;
    this.limpiarSesion();
    void this.router.navigateByUrl('/auth', { replaceUrl: true });
    if (!token) return;

    const revision = this.revisionSesion;
    this.cierreActual.set('pendiente');
    // La petición conserva solo el token necesario para revocarlo, aunque ya se borró localmente.
    this.http.post('/api/logout', {}, {
      headers: { Authorization: `Bearer ${token}` },
    }).pipe(timeout(ESPERA_REVOCACION_MS)).subscribe({
      next: () => {
        if (revision === this.revisionSesion) this.cierreActual.set('revocado');
      },
      error: (error: unknown) => {
        if (revision !== this.revisionSesion) return;
        this.cierreActual.set(error instanceof HttpErrorResponse && error.status === 401 ? 'invalido' : 'solo_local');
      },
    });
  }

  limpiarSesion(): void {
    // También cancela respuestas pendientes que podrían restaurar datos de la sesión anterior.
    if (this.token || this.usuarioActual()) this.revisionSesion++;
    localStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_USUARIO);
    this.usuarioActual.set(null);
    this.finDeSesion.next();
  }

  inicio(): string {
    const usuario = this.usuarioActual();
    return usuario ? INICIO_POR_ROL[usuario.rol] : '/auth';
  }
}

function leerUsuarioGuardado(): Usuario | null {
  if (!localStorage.getItem(CLAVE_TOKEN)) return null;
  const guardado = localStorage.getItem(CLAVE_USUARIO);
  try {
    return guardado ? (JSON.parse(guardado) as Usuario) : null;
  } catch {
    return null;
  }
}
