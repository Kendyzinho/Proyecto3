import { Routes } from '@angular/router';

import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Secretaría de Jefatura', menu: MENUS_POR_ROL.secretaria, adaptable: true },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('./inicio/inicio').then((m) => m.Inicio) },
      { path: 'usuarios', title: 'Usuarios', loadComponent: () => import('./usuarios/usuarios').then((m) => m.Usuarios) },
      { path: 'nomina', title: 'Nómina de estudiantes', loadComponent: () => import('./nomina/nomina').then((m) => m.Nomina) },
      { path: 'documentos', title: 'Documentos', loadComponent: () => import('./documentos/documentos').then((m) => m.Documentos) },
    ],
  },
] satisfies Routes;
