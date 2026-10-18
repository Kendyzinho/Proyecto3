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
  {
    path: 'solicitudes',
    title: 'Solicitudes de práctica',
    loadComponent: () => import('./solicitudes-practica/revision-solicitudes').then((m) => m.RevisionSolicitudes),
  },
  {
    path: 'practicas/nueva',
    title: 'Nueva práctica · HU-19',
    loadComponent: () => import('./solicitudes-practica/nueva-practica-hu19').then((m) => m.NuevaPracticaHu19),
  },
] satisfies Routes;
