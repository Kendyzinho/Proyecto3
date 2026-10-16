// Rol que devuelve la API. Los estudiantes no tienen rol en la tabla users: la API los marca como 'estudiante'.
export type Rol = 'secretaria' | 'coordinadora' | 'jefe' | 'estudiante';

export interface Usuario {
  id: number;
  name: string;
  email: string;
  rol: Rol;
}
