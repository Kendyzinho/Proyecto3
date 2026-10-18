import { Routes } from '@angular/router';

// Inicio provisorio hasta que exista el inicio real de este rol
export default [
  { path: '', pathMatch: 'full', redirectTo: 'centros' },
  {
    path: 'centros',
    title: 'Centros de práctica',
    loadComponent: () =>
      import('./centros-practica/centros-practica').then((m) => m.CentrosPractica),
  },
] satisfies Routes;
