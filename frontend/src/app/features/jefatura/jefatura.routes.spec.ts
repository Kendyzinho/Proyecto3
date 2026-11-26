import rutas from './jefatura.routes';
import { MENUS_POR_ROL } from '../../shared/marco/menus-por-rol';

describe('rutas de Jefatura', () => {
  const hijas = rutas[0].children.map((ruta) => ruta.path);

  it('cada opción del menú del Jefe tiene su pantalla', () => {
    for (const opcion of MENUS_POR_ROL.jefe) {
      expect(hijas).toContain(opcion.ruta.replace(/^\/jefatura\/?/, ''));
    }
  });

  it('el Jefe tiene las mismas pantallas que la Coordinadora y la Secretaría, más las mallas', () => {
    const textos = (rol: 'coordinadora' | 'secretaria' | 'jefe') => MENUS_POR_ROL[rol].map((opcion) => opcion.texto);
    const esperado = new Set([...textos('coordinadora'), ...textos('secretaria'), 'Mallas y prácticas']);

    expect(new Set(textos('jefe'))).toEqual(esperado);
  });
});
