import { Routes } from '@angular/router';

import { Marco, OpcionMenu } from '../../shared/marco/marco';

// Las opciones de Usuarios (HU-05) y Documentos (HU-10) se suman cuando existan sus pantallas
const MENU: OpcionMenu[] = [
  { texto: 'Inicio', icono: 'home', ruta: '/secretaria' },
  { texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' },
];

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Secretaría de Jefatura', menu: MENU },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('./inicio/inicio').then((m) => m.Inicio) },
      { path: 'nomina', title: 'Nómina de estudiantes', loadComponent: () => import('./nomina/nomina').then((m) => m.Nomina) },
    ],
  },
] satisfies Routes;
