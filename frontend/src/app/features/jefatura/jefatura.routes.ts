import { Routes } from '@angular/router';
import { Marco, OpcionMenu } from '../../shared/marco/marco';

const MENU: OpcionMenu[] = [{ texto: 'Inicio', icono: 'home', ruta: '/jefatura' }];

// Inicio provisorio hasta que exista el inicio real de este rol
export default [
  {
    path: '',
    component: Marco,
    data: { grupo: 'Jefatura de Carrera', menu: MENU, adaptable: true },
    children: [
      { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
    ],
  },
] satisfies Routes;
