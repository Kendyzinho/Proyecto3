import { Routes } from '@angular/router';

import { authGuard, invitadoGuard } from './core/guards/auth-guard';

// Una funcionalidad por rol, cargada bajo demanda (lazy loading).
export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'auth' },
  { path: 'auth', canMatch: [invitadoGuard], loadChildren: () => import('./features/auth/auth.routes') },
  { path: 'perfil', canMatch: [authGuard], title: 'Mi perfil', loadComponent: () => import('./features/perfil/perfil').then((m) => m.Perfil) },
  { path: 'coordinacion', canMatch: [authGuard], data: { rol: 'coordinadora' }, loadChildren: () => import('./features/coordinacion/coordinacion.routes') },
  { path: 'estudiante', canMatch: [authGuard], data: { rol: 'estudiante' }, loadChildren: () => import('./features/estudiante/estudiante.routes') },
  { path: 'secretaria', canMatch: [authGuard], data: { rol: 'secretaria' }, loadChildren: () => import('./features/secretaria/secretaria.routes') },
  { path: 'jefatura', canMatch: [authGuard], data: { rol: 'jefe' }, loadChildren: () => import('./features/jefatura/jefatura.routes') },
  // El supervisor no tiene cuenta: entra con el enlace que le llega por correo (HU-19, HU-26)
  { path: 'supervisor', loadChildren: () => import('./features/supervisor/supervisor.routes') },
  { path: '**', redirectTo: '' },
];
