import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, takeUntil, throwError } from 'rxjs';

import { Auth } from '../services/auth';

// Agrega el token de Sanctum a cada petición. Si la API responde 401 con un token
// guardado (sesión vencida o cerrada en otro lado), limpia la sesión y vuelve al login.
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(Auth);
  const router = inject(Router);
  if (!req.url.startsWith('/api/')) return next(req);

  const token = req.headers.get('Authorization')?.replace(/^Bearer /, '') ?? auth.token;

  const headers: Record<string, string> = { Accept: 'application/json' };
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const respuesta = next(req.clone({ setHeaders: headers })).pipe(
    catchError((error: HttpErrorResponse) => {
      if (error.status === 401 && token && token === auth.token) {
        auth.limpiarSesion();
        void router.navigateByUrl('/auth', { replaceUrl: true });
      }
      return throwError(() => error);
    }),
  );

  // El logout debe terminar después de limpiar la sesión; las demás peticiones ya no son útiles.
  return token && req.url !== '/api/logout'
    ? respuesta.pipe(takeUntil(auth.sesionFinalizada$))
    : respuesta;
};
