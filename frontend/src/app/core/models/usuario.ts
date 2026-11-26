// Rol que devuelve la API. Los estudiantes no tienen rol en la tabla users: la API los marca como 'estudiante'.
// HU-19: el supervisor(a) del centro es un usuario externo con cuenta propia
export type Rol = 'secretaria' | 'coordinadora' | 'jefe' | 'estudiante' | 'supervisor';

export interface Usuario {
  id: number;
  name: string;
  rut: string | null;
  email: string;
  rol: Rol;
}

// Nombre oficial de cada rol (Art. 5 del reglamento), como se muestra bajo el nombre del usuario
export const NOMBRE_ROL: Record<Rol, string> = {
  secretaria: 'Secretaria de Jefatura',
  coordinadora: 'Coordinadora de Prácticas',
  jefe: 'Jefe de Carrera',
  estudiante: 'Estudiante Practicante',
  supervisor: 'Supervisor(a) de Práctica',
};
