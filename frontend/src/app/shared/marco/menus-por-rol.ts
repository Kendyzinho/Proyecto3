import type { OpcionMenu } from './marco';

export const MENUS_POR_ROL: Record<'coordinadora' | 'secretaria' | 'jefe' | 'estudiante', OpcionMenu[]> = {
  coordinadora: [
    { texto: 'Inicio', icono: 'home', ruta: '/coordinacion' },
    { texto: 'Prácticas', icono: 'work', ruta: '/coordinacion/asignaciones' },
    { texto: 'Solicitudes', icono: 'inbox', ruta: '/coordinacion/solicitudes' },
    { texto: 'Centros de práctica', icono: 'domain', ruta: '/coordinacion/centros' },
    { texto: 'Documentos', icono: 'description', ruta: '/coordinacion/documentos' },
  ],
  secretaria: [
    { texto: 'Inicio', icono: 'home', ruta: '/secretaria' },
    { texto: 'Usuarios', icono: 'group', ruta: '/secretaria/usuarios' },
    { texto: 'Nómina de estudiantes', icono: 'table_view', ruta: '/secretaria/nomina' },
    { texto: 'Documentos', icono: 'description', ruta: '/secretaria/documentos' },
  ],
  jefe: [
    { texto: 'Inicio', icono: 'home', ruta: '/jefatura' },
    { texto: 'Mallas y prácticas', icono: 'account_tree', ruta: '/jefatura/mallas' },
  ],
  estudiante: [
    { texto: 'Inicio', icono: 'home', ruta: '/estudiante' },
    { texto: 'Solicitud de práctica', icono: 'send', ruta: '/estudiante/solicitud' },
  ],
};

// Título del menú de cada rol; lo usan sus rutas y el perfil, que es común a todos
export const GRUPOS_POR_ROL: Record<'coordinadora' | 'secretaria' | 'jefe' | 'estudiante', string> = {
  coordinadora: 'Coordinación de Prácticas',
  secretaria: 'Secretaría de Jefatura',
  jefe: 'Jefatura de Carrera',
  estudiante: 'Estudiante',
};
