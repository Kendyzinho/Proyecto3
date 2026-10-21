import { Routes } from '@angular/router';

import { Marco, OpcionMenu } from '../../shared/marco/marco';


// La opción de Usuarios (HU-05) se suma cuando exista su pantalla
const MENU: OpcionMenu[] = [
  { texto: 'Inicio', icono: 'home', ruta: '/secretaria' },
  { texto: 'Usuarios', icono: 'group', ruta: '/secretaria/usuarios' },
  { texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' },
  { texto: 'Documentos', icono: 'description', ruta: '/secretaria/documentos' },
];

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Secretaría de Jefatura', menu: MENU },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('./inicio/inicio').then((m) => m.Inicio) },
      { path: 'usuarios', title: 'Usuarios', loadComponent: () => import('./usuarios/usuarios').then((m) => m.Usuarios) },
      { path: 'nomina', title: 'Nómina de estudiantes', loadComponent: () => import('./nomina/nomina').then((m) => m.Nomina) },
      { path: 'documentos', title: 'Documentos', loadComponent: () => import('./documentos/documentos').then((m) => m.Documentos) },
    ],
  },
] satisfies Routes;
