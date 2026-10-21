import { Routes } from '@angular/router';
import { Marco } from '../../shared/marco/marco';

export default [
  {
    path: '',
    component: Marco,
    data: {
      grupo: 'Coordinadora de Prácticas',
      adaptable: true,
      menu: [
        { texto: 'Inicio', icono: 'home', ruta: '/coordinacion' },
        { texto: 'Centros de práctica', icono: 'domain', ruta: '/coordinacion/centros' },
      ],
    },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('./inicio').then((m) => m.InicioCoordinacion) },
      {
        path: 'centros',
        title: 'Centros de práctica',
        loadComponent: () => import('./centros-practica/centros-practica').then((m) => m.CentrosPractica),
      },
    ],
  },
] satisfies Routes;
