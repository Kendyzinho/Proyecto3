import { Injectable, computed, inject, signal } from '@angular/core';

import { Auth } from '../../core/services/auth';
import { CentrosPracticaService } from '../coordinacion/centros-practica/centros-practica.service';
import { HistorialDecision, SolicitudFormulario, SolicitudPractica } from './solicitud-practica.model';

const CLAVES = {
  solicitudes: 'demo_solicitudes_practica',
  historial: 'demo_historial_practica',
  practicas: 'demo_practicas_solicitud',
} as const;

function leer<T>(clave: string): T[] {
  try {
    const contenido = typeof localStorage === 'undefined' ? null : localStorage.getItem(clave);
    return contenido ? JSON.parse(contenido) as T[] : [];
  } catch {
    return [];
  }
}

@Injectable({ providedIn: 'root' })
export class SolicitudesPracticaService {
  private readonly auth = inject(Auth);
  private readonly catalogoCentros = inject(CentrosPracticaService);
  readonly todosLosCentros = this.catalogoCentros.listar();
  readonly centros = this.todosLosCentros.filter((centro) => centro.estado === 'Activo' && (centro.esInterno || centro.acuerdo === 'Vigente'));
  readonly habilitacion = signal({ puedePostular: true, motivo: 'Debes aprobar la asignatura prerrequisito para solicitar una práctica profesional.' });
  readonly solicitudes = signal<SolicitudPractica[]>(leer<SolicitudPractica>(CLAVES.solicitudes));
  readonly historial = signal<HistorialDecision[]>(leer<HistorialDecision>(CLAVES.historial));
  readonly practicas = signal<{ solicitudId: number; origen: 'solicitud'; estado: 'Por revisar' | 'Devuelta' | 'Aprobada'; horario: SolicitudPractica['horario'] }[]>(leer(CLAVES.practicas));
  readonly solicitudActual = computed(() => {
    const email = this.auth.usuario()?.email;
    return email ? this.solicitudes().find((solicitud) => solicitud.estudianteCorreo === email) ?? null : null;
  });
  private siguienteId = 1;

  constructor() {
    this.siguienteId = Math.max(0, ...this.solicitudes().map((item) => item.id)) + 1;
  }

  guardarBorrador(formulario: SolicitudFormulario): SolicitudPractica {
    return this.guardar(formulario, 'Borrador');
  }

  enviar(formulario: SolicitudFormulario): SolicitudPractica {
    const actual = this.solicitudActual();
    const estado = actual?.estado === 'Devuelta' ? 'Por revisar' : 'Por revisar';
    const solicitud = this.guardar(formulario, estado);
    this.practicas.update((items) => {
      const practica = { solicitudId: solicitud.id, origen: 'solicitud' as const, estado: 'Por revisar' as const, horario: structuredClone(solicitud.horario) };
      return items.some((item) => item.solicitudId === solicitud.id) ? items.map((item) => item.solicitudId === solicitud.id ? practica : item) : [practica, ...items];
    });
    if (actual?.estado === 'Devuelta') {
      this.historial.update((items) => [{ solicitudId: solicitud.id, decision: 'Reenviada', motivo: null, responsable: this.auth.usuario()?.name ?? 'Estudiante', fecha: new Date().toISOString() }, ...items]);
    }
    this.persistir();
    return solicitud;
  }

  aprobar(id: number, responsable: string): void {
    this.solicitudes.update((items) => items.map((item) => item.id === id ? { ...item, estado: 'Aprobada', motivoDevolucion: null, actualizadaEn: new Date().toISOString() } : item));
    this.historial.update((items) => [{ solicitudId: id, decision: 'Aprobada', motivo: null, responsable, fecha: new Date().toISOString() }, ...items]);
    this.actualizarEstadoPractica(id, 'Aprobada');
    this.persistir();
  }

  devolver(id: number, motivo: string, responsable: string): void {
    this.solicitudes.update((items) => items.map((item) => item.id === id ? { ...item, estado: 'Devuelta', motivoDevolucion: motivo.trim(), actualizadaEn: new Date().toISOString() } : item));
    this.historial.update((items) => [{ solicitudId: id, decision: 'Devuelta', motivo: motivo.trim(), responsable, fecha: new Date().toISOString() }, ...items]);
    this.actualizarEstadoPractica(id, 'Devuelta');
    this.persistir();
  }

  private guardar(formulario: SolicitudFormulario, estado: SolicitudPractica['estado']): SolicitudPractica {
    const usuario = this.auth.usuario();
    const email = usuario?.email ?? 'estudiante@alumnos.uta.cl';
    const existente = this.solicitudes().find((item) => item.estudianteCorreo === email);
    const solicitud: SolicitudPractica = {
      ...formulario,
      id: existente?.id ?? this.siguienteId++,
      estudianteNombre: usuario?.name ?? 'Estudiante de prueba',
      estudianteCorreo: email,
      estado,
      habilitacionCumplida: this.habilitacion().puedePostular,
      motivoDevolucion: existente?.estado === 'Devuelta' && estado === 'Devuelta' ? existente.motivoDevolucion : null,
      actualizadaEn: new Date().toISOString(),
    };
    this.solicitudes.update((items) => existente ? items.map((item) => item.id === existente.id ? solicitud : item) : [solicitud, ...items]);
    this.persistir();
    return solicitud;
  }

  private actualizarEstadoPractica(id: number, estado: 'Devuelta' | 'Aprobada'): void {
    this.practicas.update((items) => items.map((item) => item.solicitudId === id ? { ...item, estado } : item));
  }

  private persistir(): void {
    try {
      localStorage.setItem(CLAVES.solicitudes, JSON.stringify(this.solicitudes()));
      localStorage.setItem(CLAVES.historial, JSON.stringify(this.historial()));
      localStorage.setItem(CLAVES.practicas, JSON.stringify(this.practicas()));
    } catch {
      // La bandeja sigue disponible en memoria si el navegador bloquea el almacenamiento local.
    }
  }
}
