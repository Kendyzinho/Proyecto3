import { Routes } from '@angular/router';
import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

export default [
  {
    path: '',
    component: Marco,
    data: {
      grupo: 'Coordinadora de Prácticas',
      adaptable: true,
      menu: MENUS_POR_ROL.coordinadora,
    },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('./inicio').then((m) => m.InicioCoordinacion) },
      {
        path: 'asignaciones',
        title: 'Asignación de prácticas',
        loadComponent: () => import('./asignacion-practicas/asignacion-practicas').then((m) => m.AsignacionPracticas),
      },
      {
        path: 'centros',
        title: 'Centros de práctica',
        loadComponent: () => import('./centros-practica/centros-practica').then((m) => m.CentrosPractica),
      },
      {
        path: 'solicitudes',
        title: 'Solicitudes de práctica',
        loadComponent: () => import('./solicitudes-practica/revision-solicitudes').then((m) => m.RevisionSolicitudes),
      },
      {
        path: 'practicas',
        children: [
          {
            path: 'nueva',
            title: 'Nueva práctica · HU-19',
            loadComponent: () => import('./solicitudes-practica/nueva-practica-hu19').then((m) => m.NuevaPracticaHu19),
          },
        ],
      },
    ],
  },
] satisfies Routes;
