// Rol que devuelve la API. Los estudiantes no tienen rol en la tabla users: la API los marca como 'estudiante'.
export type Rol = 'secretaria' | 'coordinadora' | 'jefe' | 'estudiante';

export const NOMBRE_POR_ROL: Record<Rol, string> = {
  secretaria: 'Secretaria de Jefatura',
  coordinadora: 'Coordinadora de Prácticas',
  jefe: 'Jefe de Carrera',
  estudiante: 'Estudiante',
};

export interface Usuario {
  id: number;
  name: string;
  rut: string | null;
  email: string;
  rol: Rol;
}
