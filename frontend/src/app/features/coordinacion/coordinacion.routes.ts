import { Routes } from '@angular/router';

export default [
  { path: '', pathMatch: 'full', redirectTo: 'asignaciones' },
  {
    path: 'asignaciones',
    title: 'Asignación de prácticas',
    loadComponent: () =>
      import('./asignacion-practicas/asignacion-practicas').then((m) => m.AsignacionPracticas),
  },
] satisfies Routes;
