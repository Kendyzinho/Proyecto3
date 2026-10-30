import type { OpcionMenu } from './marco';

export const MENUS_POR_ROL: Record<'coordinadora' | 'secretaria' | 'jefe' | 'estudiante', OpcionMenu[]> = {
  coordinadora: [
    { texto: 'Inicio', icono: 'home', ruta: '/coordinacion' },
    { texto: 'Prácticas', icono: 'work', ruta: '/coordinacion/asignaciones' },
    { texto: 'Solicitudes', icono: 'inbox', ruta: '/coordinacion/solicitudes' },
    { texto: 'Centros de práctica', icono: 'domain', ruta: '/coordinacion/centros' },
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
