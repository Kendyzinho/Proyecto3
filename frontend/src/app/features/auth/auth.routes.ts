import { Routes } from '@angular/router';

export default [
  { path: '', title: 'Iniciar sesión', loadComponent: () => import('./login/login').then((m) => m.Login) },
] satisfies Routes;
