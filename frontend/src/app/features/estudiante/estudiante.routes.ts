import { Routes } from '@angular/router';

import { Marco } from '../../shared/marco/marco';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Estudiante', menu: MENUS_POR_ROL.estudiante, adaptable: true },
    children: [
      { path: 'portafolio', title: 'Portafolio digital', loadComponent: () => import('./portafolio/portafolio').then((m) => m.Portafolio) },
      { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
      { path: 'solicitud', title: 'Solicitud de práctica', loadComponent: () => import('./solicitud-practica/solicitud-estudiante').then((m) => m.SolicitudEstudiante) },
      { path: 'percepcion-centro', title: 'Percepción del centro', loadComponent: () => import('./percepcion-centro/percepcion-centro').then((m) => m.PercepcionCentro) },
    ],
  },
] satisfies Routes;
