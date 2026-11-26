import type { OpcionMenu } from './marco';

export const MENUS_POR_ROL: Record<'coordinadora' | 'secretaria' | 'jefe' | 'estudiante', OpcionMenu[]> = {
  coordinadora: [
    { texto: 'Inicio', icono: 'home', ruta: '/coordinacion' },
    { texto: 'Prácticas', icono: 'work', ruta: '/coordinacion/asignaciones' },
    { texto: 'Lista general', icono: 'list_alt', ruta: '/coordinacion/practicas' },
    { texto: 'Solicitudes', icono: 'inbox', ruta: '/coordinacion/solicitudes' },
    { texto: 'Alertas', icono: 'notifications_active', ruta: '/coordinacion/alertas' },
    { texto: 'Centros de práctica', icono: 'domain', ruta: '/coordinacion/centros' },
    { texto: 'Documentos', icono: 'description', ruta: '/coordinacion/documentos' },
  ],
  secretaria: [
    { texto: 'Inicio', icono: 'home', ruta: '/secretaria' },
    { texto: 'Usuarios', icono: 'group', ruta: '/secretaria/usuarios' },
    { texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' },
    { texto: 'Documentos', icono: 'description', ruta: '/secretaria/documentos' },
  ],
  // El Jefe de Carrera ve y hace todo lo de la Coordinadora y la Secretaría (reunión 4), dentro de su propio marco
  jefe: [
    { texto: 'Inicio', icono: 'home', ruta: '/jefatura' },
    { texto: 'Prácticas', icono: 'work', ruta: '/jefatura/asignaciones' },
    { texto: 'Solicitudes', icono: 'inbox', ruta: '/jefatura/solicitudes' },
    { texto: 'Centros de práctica', icono: 'domain', ruta: '/jefatura/centros' },
    { texto: 'Usuarios', icono: 'group', ruta: '/jefatura/usuarios' },
    { texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/jefatura/nomina' },
    { texto: 'Documentos', icono: 'description', ruta: '/jefatura/documentos' },
    { texto: 'Mallas y prácticas', icono: 'account_tree', ruta: '/jefatura/mallas' },
  ],
  estudiante: [
    { texto: 'Inicio', icono: 'home', ruta: '/estudiante' },
    { texto: 'Mi bitácora', icono: 'schedule', ruta: '/estudiante/bitacora' },
    { texto: 'Portafolio digital', icono: 'link', ruta: '/estudiante/portafolio' },
    { texto: 'Solicitud de práctica', icono: 'send', ruta: '/estudiante/solicitud' },
    { texto: 'Percepción del centro', icono: 'rate_review', ruta: '/estudiante/percepcion-centro' },
  ],
};

// Título del menú de cada rol; lo usan sus rutas y el perfil, que es común a todos
export const GRUPOS_POR_ROL: Record<'coordinadora' | 'secretaria' | 'jefe' | 'estudiante', string> = {
  coordinadora: 'Coordinación de Prácticas',
  secretaria: 'Secretaría de Jefatura',
  jefe: 'Jefatura de Carrera',
  estudiante: 'Estudiante',
};
