import { Routes } from '@angular/router';
import { Marco } from '../../shared/marco/marco';

export default [
  {
    path: '', component: Marco,
    data: { grupo: 'Coordinadora de Prácticas', adaptable: true, menu: [{ texto: 'Inicio', icono: 'home', ruta: '/coordinacion' }] },
    children: [{ path: '', title: 'Inicio', loadComponent: () => import('./inicio').then((m) => m.InicioCoordinacion) }],
  },
] satisfies Routes;
