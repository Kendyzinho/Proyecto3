import { Routes } from '@angular/router';

import { authGuard } from '../../core/guards/auth-guard';
import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

export default [
  // HU-26: confirmación semanal de asistencia desde el enlace firmado del correo (sin sesión)
  {
    path: 'asistencia/:practica/:semana',
    title: 'Confirmar asistencia',
    loadComponent: () => import('./confirmacion-semanal/confirmacion-semanal').then((m) => m.ConfirmacionSemanal),
  },
  // HU-19: el supervisor(a) entra con su cuenta
  {
    path: '',
    canMatch: [authGuard],
    data: { rol: 'supervisor', grupo: 'Supervisión de Prácticas', menu: MENUS_POR_ROL.supervisor, adaptable: true },
    component: Marco,
    children: [
      { path: '', title: 'Informes confidenciales', loadComponent: () => import('./informes/informes-supervisor').then((m) => m.InformesSupervisor) },
      {
        path: 'informes/:practica',
        title: 'Informe confidencial',
        loadComponent: () => import('./informes/informe-supervisor').then((m) => m.InformeSupervisor),
      },
    ],
  },
] satisfies Routes;
