import { Routes } from '@angular/router';

export default [
  // HU-26: confirmación semanal de asistencia desde el enlace del correo
  {
    path: 'asistencia/:practica/:semana',
    title: 'Confirmar asistencia',
    loadComponent: () => import('./confirmacion-semanal/confirmacion-semanal').then((m) => m.ConfirmacionSemanal),
  },
] satisfies Routes;
