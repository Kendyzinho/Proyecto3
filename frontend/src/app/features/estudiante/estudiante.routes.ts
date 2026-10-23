import { Routes } from '@angular/router';
import { Marco, OpcionMenu } from '../../shared/marco/marco';

const MENU: OpcionMenu[] = [
  { texto: 'Inicio', icono: 'home', ruta: '/estudiante' },
  { texto: 'Solicitud de práctica', icono: 'send', ruta: '/estudiante/solicitud' },
];

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Estudiante', menu: MENU, adaptable: true },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
      { path: 'solicitud', title: 'Solicitud de práctica', loadComponent: () => import('./solicitud-practica/solicitud-estudiante').then((m) => m.SolicitudEstudiante) },
    ],
  },
] satisfies Routes;
