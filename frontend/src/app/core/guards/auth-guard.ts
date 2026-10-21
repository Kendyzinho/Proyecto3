import { inject } from '@angular/core';
import { CanMatchFn, Router } from '@angular/router';

import { Rol } from '../models/usuario';
import { Auth } from '../services/auth';

// Pide sesión iniciada y, si la ruta define data.rol, que el usuario tenga ese rol.
export const authGuard: CanMatchFn = (route) => {
  const auth = inject(Auth);
  const router = inject(Router);
  const usuario = auth.usuario();

  if (!usuario || !auth.token) {
    return router.createUrlTree(['/auth']);
  }
  const rol = route.data?.['rol'] as Rol | undefined;
  if (rol && usuario.rol !== rol) {
    return router.createUrlTree([auth.inicio()]);
  }
  return true;
};

// El login solo se muestra sin sesión; con sesión manda al inicio de su rol.
export const invitadoGuard: CanMatchFn = () => {
  const auth = inject(Auth);
  return auth.autenticado() && auth.token ? inject(Router).createUrlTree([auth.inicio()]) : true;
};
