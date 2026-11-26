import { Routes } from '@angular/router';
import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

export default [
  {
    path: '',
    component: Marco,
    data: {
      grupo: 'Coordinación de Prácticas',
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
        path: 'practicas',
        title: 'Lista general de prácticas',
        loadComponent: () => import('./listado-practicas/listado-practicas').then((m) => m.ListadoPracticas),
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
      { path: 'alertas', title: 'Alertas', loadComponent: () => import('./alertas/alertas').then((m) => m.Alertas) },
      { path: 'visitas', title: 'Visitas técnicas', loadComponent: () => import('./visitas/visitas').then((m) => m.Visitas) },

      {
        path: 'documentos',
        title: 'Documentos',
        loadComponent: () => import('../secretaria/documentos/documentos').then((m) => m.Documentos),
      },
    ],
  },
] satisfies Routes;
