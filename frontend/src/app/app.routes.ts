import { Routes } from '@angular/router';

// Una funcionalidad por rol, cargada bajo demanda (lazy loading).
// Los guards por rol se agregan en core/guards cuando exista la autenticación (HU-01).
export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'auth' },
  { path: 'auth', loadChildren: () => import('./features/auth/auth.routes') },
  { path: 'coordinacion', loadChildren: () => import('./features/coordinacion/coordinacion.routes') },
  { path: 'estudiante', loadChildren: () => import('./features/estudiante/estudiante.routes') },
  { path: 'secretaria', loadChildren: () => import('./features/secretaria/secretaria.routes') },
  { path: 'jefatura', loadChildren: () => import('./features/jefatura/jefatura.routes') },
  { path: 'supervisor', loadChildren: () => import('./features/supervisor/supervisor.routes') },
];
