import { Routes } from '@angular/router';

export default [
  { path: '', title: 'Iniciar sesión', loadComponent: () => import('./login/login').then((m) => m.Login) },
  // HU-02: recuperación de contraseña
  { path: 'recuperar', title: 'Recuperar contraseña', loadComponent: () => import('./recuperar/recuperar').then((m) => m.Recuperar) },
  { path: 'restablecer', title: 'Nueva contraseña', loadComponent: () => import('./restablecer/restablecer').then((m) => m.Restablecer) },
] satisfies Routes;
