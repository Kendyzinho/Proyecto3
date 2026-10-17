// Rol que devuelve la API. Los estudiantes no tienen rol en la tabla users: la API los marca como 'estudiante'.
export type Rol = 'secretaria' | 'coordinadora' | 'jefe' | 'estudiante';

export interface Usuario {
  id: number;
  name: string;
  email: string;
  rol: Rol;
}

// Nombre oficial de cada rol (Art. 5 del reglamento), como se muestra bajo el nombre del usuario
export const NOMBRE_ROL: Record<Rol, string> = {
  secretaria: 'Secretaria de Jefatura',
  coordinadora: 'Coordinadora de Prácticas',
  jefe: 'Jefe de Carrera',
  estudiante: 'Estudiante Practicante',
};
