import { Routes } from '@angular/router';

import { Marco, OpcionMenu } from '../../shared/marco/marco';

// Mi asistencia, Evaluación del centro y Portafolio se suman cuando existan sus pantallas
const MENU: OpcionMenu[] = [
  { texto: 'Inicio', icono: 'home', ruta: '/estudiante' },
  { texto: 'Solicitud de práctica', icono: 'send', ruta: '/estudiante/solicitud' },
];

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Estudiante', menu: MENU },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
      { path: 'solicitud', title: 'Solicitud de práctica', loadComponent: () => import('./solicitud-practica/solicitud-estudiante').then((m) => m.SolicitudEstudiante) },
    ],
  },
] satisfies Routes;
