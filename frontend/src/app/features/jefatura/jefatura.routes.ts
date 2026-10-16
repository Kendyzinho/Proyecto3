import { Routes } from '@angular/router';

// Inicio provisorio hasta que exista el inicio real de este rol
export default [
  { path: '', title: 'Inicio', loadComponent: () => import('../../shared/inicio-temporal/inicio-temporal').then((m) => m.InicioTemporal) },
] satisfies Routes;
