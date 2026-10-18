import { Injectable, signal } from '@angular/core';

import { AsignacionPractica, CentroAsignable, EstudianteAsignable } from './asignacion-practicas.model';

// Fixture de interfaz basado en la vista HU-08. Los datos reales llegarán desde Laravel.
@Injectable({ providedIn: 'root' })
export class AsignacionPracticasService {
  readonly estudiantes = signal<EstudianteAsignable[]>([
    { id: 101, nombre: 'Camila Rojas Pizarro', carrera: 'Diseño Multimedia', categoria: 'Laboral', periodo: '2° semestre 2026', areas: ['Diseño gráfico y diagramación', 'Fotografía'], habilitado: true, practicaAsignada: false },
    { id: 102, nombre: 'Fernanda Quispe Apaza', carrera: 'Diseño Multimedia', categoria: 'Laboral', periodo: '2° semestre 2026', areas: ['Fotografía', 'Producción audiovisual'], habilitado: true, practicaAsignada: false },
    { id: 103, nombre: 'Javier Mamani Condori', carrera: 'Diseño Multimedia', categoria: 'Laboral', periodo: '2° semestre 2026', areas: ['Producción audiovisual'], habilitado: true, practicaAsignada: false },
    { id: 104, nombre: 'Pedro Lagos Rivas', carrera: 'Diseño Multimedia', categoria: 'Laboral', periodo: '2° semestre 2026', areas: ['Fotografía'], habilitado: true, practicaAsignada: false },
    { id: 105, nombre: 'Sebastián Flores Tapia', carrera: 'Diseño Multimedia', categoria: 'Laboral', periodo: '2° semestre 2026', areas: ['Producción audiovisual', 'Diseño gráfico y diagramación'], habilitado: true, practicaAsignada: false },
    { id: 106, nombre: 'Estudiante con asignación previa', carrera: 'Diseño Multimedia', categoria: 'Laboral', periodo: '2° semestre 2026', areas: ['Fotografía'], habilitado: true, practicaAsignada: true },
    { id: 107, nombre: 'Matías Herrera Lillo', carrera: 'Diseño Multimedia', categoria: 'Profesional', periodo: '2° semestre 2026', areas: ['Diseño editorial', 'Diseño y desarrollo web'], habilitado: true, practicaAsignada: false },
    { id: 108, nombre: 'Valentina Contreras Rojas', carrera: 'Diseño Multimedia', categoria: 'Profesional', periodo: '2° semestre 2026', areas: ['Diseño editorial', 'Diseño gráfico y diagramación'], habilitado: true, practicaAsignada: false },
  ]);

  readonly centros = signal<CentroAsignable[]>([
    { id: 201, nombre: 'Municipalidad de Arica', rut: '76.345.821-9', estado: 'Activo', acuerdoVigente: true, areas: ['Diseño gráfico y diagramación', 'Fotografía'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Laboral', total: 3, ocupados: 1 }] },
    { id: 202, nombre: 'Imprenta Sur', rut: '77.204.631-5', estado: 'Activo', acuerdoVigente: true, areas: ['Diseño gráfico y diagramación'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Laboral', total: 2, ocupados: 0 }] },
    { id: 203, nombre: 'Productora Norte Visual', rut: '76.891.230-4', estado: 'Activo', acuerdoVigente: true, areas: ['Fotografía', 'Producción audiovisual'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Laboral', total: 4, ocupados: 0 }] },
    { id: 204, nombre: 'Corfo Arica y Parinacota', rut: '77.500.194-2', estado: 'Activo', acuerdoVigente: true, areas: ['Fotografía'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Laboral', total: 3, ocupados: 0 }] },
    { id: 205, nombre: 'Estudio Pixel Atacama', rut: '76.120.490-2', estado: 'Activo', acuerdoVigente: false, areas: ['Diseño gráfico y diagramación', 'Fotografía'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Laboral', total: 3, ocupados: 0 }] },
    { id: 206, nombre: 'Agencia Cobalto', rut: '77.902.310-7', estado: 'Dado de baja', acuerdoVigente: true, areas: ['Diseño gráfico y diagramación', 'Fotografía'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Laboral', total: 3, ocupados: 0 }] },
    { id: 207, nombre: 'Editorial Horizonte', rut: '77.321.450-8', estado: 'Activo', acuerdoVigente: true, areas: ['Diseño editorial', 'Diseño gráfico y diagramación'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Profesional', total: 2, ocupados: 0 }] },
    { id: 208, nombre: 'Estudio Web Arica', rut: '76.830.120-6', estado: 'Activo', acuerdoVigente: true, areas: ['Diseño y desarrollo web'], cupos: [{ periodo: '2° semestre 2026', categoria: 'Profesional', total: 2, ocupados: 0 }] },
  ]);

  readonly asignaciones = signal<AsignacionPractica[]>([]);

  crearAsignacion(estudianteId: number, centroId: number): string | null {
    const estudiante = this.estudiantes().find((item) => item.id === estudianteId);
    const centro = this.centros().find((item) => item.id === centroId);
    if (!estudiante || !centro) return 'Selecciona un estudiante y un centro válidos.';
    if (!estudiante.habilitado) return 'El estudiante no cumple el prerrequisito de habilitación.';
    if (estudiante.practicaAsignada) return 'Este estudiante ya tiene una práctica asignada.';
    if (centro.estado !== 'Activo' || !centro.acuerdoVigente) return 'El centro debe estar activo y tener un acuerdo vigente.';
    if (!centro.areas.some((area) => estudiante.areas.includes(area))) return 'El centro no tiene áreas coincidentes con el perfil del estudiante.';
    const cupo = centro.cupos.find((item) => item.periodo === estudiante.periodo && item.categoria === estudiante.categoria);
    if (!cupo || cupo.ocupados >= cupo.total) return 'El centro no tiene cupos disponibles para este período y categoría.';

    this.asignaciones.update((items) => [{ estudianteId, centroId, periodo: estudiante.periodo, categoria: estudiante.categoria, creadaEn: new Date().toISOString() }, ...items]);
    this.estudiantes.update((items) => items.map((item) => item.id === estudianteId ? { ...item, practicaAsignada: true } : item));
    this.centros.update((items) => items.map((item) => item.id === centroId ? { ...item, cupos: item.cupos.map((c) => c === cupo ? { ...c, ocupados: c.ocupados + 1 } : c) } : item));
    return null;
  }
}
