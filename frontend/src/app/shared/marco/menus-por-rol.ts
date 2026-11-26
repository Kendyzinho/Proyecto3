import type { Rol } from '../../core/models/usuario';
import type { OpcionMenu } from './marco';

export const MENUS_POR_ROL: Record<Rol, OpcionMenu[]> = {
  coordinadora: [
    { texto: 'Inicio', icono: 'home', ruta: '/coordinacion' },
    { texto: 'Prácticas', icono: 'work', ruta: '/coordinacion/asignaciones' },
    { texto: 'Lista general', icono: 'list_alt', ruta: '/coordinacion/practicas' },
    { texto: 'Solicitudes', icono: 'inbox', ruta: '/coordinacion/solicitudes' },
    { texto: 'Alertas', icono: 'notifications_active', ruta: '/coordinacion/alertas' },
    { texto: 'Visitas', icono: 'event', ruta: '/coordinacion/visitas' },
    { texto: 'Informes confidenciales', icono: 'assignment', ruta: '/coordinacion/informes' },
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
    { texto: 'Lista general', icono: 'list_alt', ruta: '/jefatura/practicas' },
    { texto: 'Solicitudes', icono: 'inbox', ruta: '/jefatura/solicitudes' },
    { texto: 'Alertas', icono: 'notifications_active', ruta: '/jefatura/alertas' },
    { texto: 'Visitas', icono: 'event', ruta: '/jefatura/visitas' },
    { texto: 'Informes confidenciales', icono: 'assignment', ruta: '/jefatura/informes' },
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
  // HU-19: el supervisor(a) solo ve los informes que le pidieron
  supervisor: [
    { texto: 'Informes confidenciales', icono: 'assignment', ruta: '/supervisor' },
  ],
};

// Título del menú de cada rol; lo usan sus rutas y el perfil, que es común a todos
export const GRUPOS_POR_ROL: Record<Rol, string> = {
  coordinadora: 'Coordinación de Prácticas',
  secretaria: 'Secretaría de Jefatura',
  jefe: 'Jefatura de Carrera',
  estudiante: 'Estudiante',
  supervisor: 'Supervisión de Prácticas',
};
