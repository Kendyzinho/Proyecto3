import { Routes } from '@angular/router';

import { Marco, OpcionMenu } from '../../shared/marco/marco';

const MENU: OpcionMenu[] = [
  { texto: 'Inicio', icono: 'home', ruta: '/jefatura' },
  { texto: 'Mallas y prácticas', icono: 'account_tree', ruta: '/jefatura/mallas' },
];

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Jefatura de Carrera', menu: MENU, adaptable: true },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
      { path: 'mallas', title: 'Mallas y prácticas', loadComponent: () => import('./mallas/mallas').then((m) => m.Mallas) },
    ],
  },
] satisfies Routes;
