import { Routes } from '@angular/router';

export default [
  { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
  { path: 'solicitud', title: 'Solicitud de práctica', loadComponent: () => import('./solicitud-practica/solicitud-estudiante').then((m) => m.SolicitudEstudiante) },
] satisfies Routes;
