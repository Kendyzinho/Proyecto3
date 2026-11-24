import { Routes } from '@angular/router';

import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

// Las pantallas de Coordinación y Secretaría se reutilizan tal cual; solo cambia el marco
export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Jefatura de Carrera', menu: MENUS_POR_ROL.jefe, adaptable: true },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('../coordinacion/inicio').then((m) => m.InicioCoordinacion) },
      {
        path: 'asignaciones',
        title: 'Asignación de prácticas',
        loadComponent: () => import('../coordinacion/asignacion-practicas/asignacion-practicas').then((m) => m.AsignacionPracticas),
      },
      {
        path: 'solicitudes',
        title: 'Solicitudes de práctica',
        loadComponent: () => import('../coordinacion/solicitudes-practica/revision-solicitudes').then((m) => m.RevisionSolicitudes),
      },
      {
        path: 'centros',
        title: 'Centros de práctica',
        loadComponent: () => import('../coordinacion/centros-practica/centros-practica').then((m) => m.CentrosPractica),
      },
      { path: 'usuarios', title: 'Usuarios', loadComponent: () => import('../secretaria/usuarios/usuarios').then((m) => m.Usuarios) },
      { path: 'nomina', title: 'Nómina de estudiantes', loadComponent: () => import('../secretaria/nomina/nomina').then((m) => m.Nomina) },
      { path: 'documentos', title: 'Documentos', loadComponent: () => import('../secretaria/documentos/documentos').then((m) => m.Documentos) },
      { path: 'mallas', title: 'Mallas y prácticas', loadComponent: () => import('./mallas/mallas').then((m) => m.Mallas) },
    ],
  },
] satisfies Routes;
