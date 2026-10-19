import { Routes } from '@angular/router';

export default [
  { path: '', pathMatch: 'full', redirectTo: 'centros' },
  {
    path: 'centros',
    title: 'Centros de práctica',
    loadComponent: () =>
      import('./centros-practica/centros-practica').then((m) => m.CentrosPractica),
  },
] satisfies Routes;
