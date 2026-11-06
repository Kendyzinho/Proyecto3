import { Routes } from '@angular/router';

import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Jefatura de Carrera', menu: MENUS_POR_ROL.jefe, adaptable: true },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
      { path: 'mallas', title: 'Mallas y prácticas', loadComponent: () => import('./mallas/mallas').then((m) => m.Mallas) },
    ],
  },
] satisfies Routes;
