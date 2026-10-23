import { CommonModule } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { RouterLink } from '@angular/router';

import { CentroPractica, SupervisorCentro } from '../../coordinacion/centros-practica/centro-practica.model';
import { SolicitudFormulario, SolicitudPractica } from '../../solicitudes-practica/solicitud-practica.model';
import { SolicitudesPracticaService } from '../../solicitudes-practica/solicitudes-practica.service';

const DIAS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

@Component({
  selector: 'app-solicitud-estudiante',
  imports: [CommonModule, FormsModule, MatIconModule, RouterLink],
  templateUrl: './solicitud-estudiante.html',
  styleUrl: './solicitud-estudiante.scss',
})
export class SolicitudEstudiante {
  protected readonly service = inject(SolicitudesPracticaService);
  protected readonly centros = this.service.centros;
  protected readonly dias = DIAS;
  protected readonly intentoEnviar = signal(false);
  protected readonly aviso = signal('');
  protected readonly avisoExitoso = signal(false);
  protected readonly mostrarFormularioCentroPropuesto = signal(true);
  protected readonly solicitud = this.service.solicitudActual;
  protected readonly bloqueada = computed(() => ['Por revisar', 'Aprobada', 'En curso', 'Finalizada', 'Cancelada'].includes(this.solicitud()?.estado ?? ''));
  protected formulario: SolicitudFormulario = this.formularioVacio();
  private centroRegistradoAnterior: number | null = null;

  constructor() {
    this.service.cargarEstudiante().subscribe({
      next: () => this.cargarFormularioDesdeBackend(),
      error: () => {},
    });
  }

  protected seleccionarCentro(id: number | null): void {
    if (id === null) {
      this.proponerCentro();
      return;
    }
    this.formulario.modoCentro = 'Registrado';
    this.formulario.centroId = id;
    if (id !== null) this.centroRegistradoAnterior = id;
    this.mostrarFormularioCentroPropuesto.set(false);
    const centro = this.centros().find((item) => item.id === id);
    this.formulario.centroRazonSocial = centro?.razonSocial ?? '';
    this.formulario.centroRut = centro?.rut ?? '';
    const supervisor = centro?.supervisores.find((item) => item.estado === 'Activo');
    this.formulario.modoSupervisor = supervisor ? 'Registrado' : 'Propuesto';
    this.formulario.supervisorId = supervisor?.correo ?? null;
    if (supervisor) this.aplicarSupervisor(supervisor);
    else this.limpiarSupervisor();
  }

  protected proponerCentro(): void {
    this.centroRegistradoAnterior = this.formulario.centroId ?? this.centroRegistradoAnterior;
    this.formulario.modoCentro = 'Propuesto';
    this.formulario.centroId = null;
    this.formulario.centroRazonSocial = '';
    this.formulario.centroRut = '';
    this.formulario.modoSupervisor = 'Propuesto';
    this.formulario.supervisorId = null;
    this.limpiarSupervisor();
    this.mostrarFormularioCentroPropuesto.set(true);
  }

  protected volverACentroRegistrado(): void {
    const centro = this.centros().find((item) => item.id === this.centroRegistradoAnterior) ?? this.centros()[0];
    this.seleccionarCentro(centro?.id ?? null);
  }

  protected alternarFormularioCentroPropuesto(): void {
    this.mostrarFormularioCentroPropuesto.update((mostrar) => !mostrar);
  }

  protected seleccionarSupervisor(correo: string): void {
    if (!correo) {
      this.proponerSupervisor();
      return;
    }
    this.formulario.modoSupervisor = 'Registrado';
    this.formulario.supervisorId = correo || null;
    const supervisor = this.supervisoresDisponibles().find((item) => item.correo === correo);
    if (supervisor) this.aplicarSupervisor(supervisor);
  }

  protected centroSeleccionado(): CentroPractica | null {
    return this.centros().find((centro) => centro.id === this.formulario.centroId) ?? null;
  }

  protected supervisoresDisponibles(): SupervisorCentro[] {
    return (this.centroSeleccionado()?.supervisores ?? []).filter((supervisor) => supervisor.estado === 'Activo');
  }

  protected supervisorSeleccionado(): SupervisorCentro | null {
    return this.supervisoresDisponibles().find((supervisor) => supervisor.correo === this.formulario.supervisorId) ?? null;
  }

  protected proponerSupervisor(): void {
    this.formulario.modoSupervisor = 'Propuesto';
    this.formulario.supervisorId = null;
    this.limpiarSupervisor();
  }

  protected alternarDia(dia: string): void {
    this.formulario.horario.dias = this.formulario.horario.dias.includes(dia)
      ? this.formulario.horario.dias.filter((item) => item !== dia)
      : [...this.formulario.horario.dias, dia];
  }

  protected iniciales(nombre: string): string {
    return nombre.split(/\s+/).filter(Boolean).slice(0, 2).map((parte) => parte[0]).join('').toLocaleUpperCase('es');
  }

  protected formatearRut(): void {
    const limpio = this.formulario.centroRut.replace(/[^0-9kK]/g, '').toUpperCase();
    if (limpio.length < 2) return;
    this.formulario.centroRut = `${limpio.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}-${limpio.slice(-1)}`;
  }

  protected rutValido(): boolean {
    const limpio = this.formulario.centroRut.replace(/[.\-\s]/g, '').toUpperCase();
    if (!/^\d{7,8}[0-9K]$/.test(limpio)) return false;
    let suma = 0;
    let factor = 2;
    for (let indice = limpio.length - 2; indice >= 0; indice--) {
      suma += Number(limpio[indice]) * factor;
      factor = factor === 7 ? 2 : factor + 1;
    }
    const resto = 11 - (suma % 11);
    const dv = resto === 11 ? '0' : resto === 10 ? 'K' : String(resto);
    return dv === limpio.slice(-1);
  }

  protected centroValido(): boolean {
    return this.formulario.modoCentro === 'Registrado'
      ? this.centroSeleccionado()?.estado === 'Activo'
      : !!this.formulario.centroRazonSocial.trim() && this.rutValido();
  }

  protected supervisorValido(): boolean {
    if (this.formulario.modoSupervisor === 'Registrado') return this.supervisorSeleccionado() !== null;
    return !!this.formulario.supervisorNombre.trim() && this.correoValido(this.formulario.supervisorCorreo);
  }

  protected horarioValido(): boolean {
    const horario = this.formulario.horario;
    return !!horario.modalidad && !!horario.fechaInicio && !!horario.fechaTermino && horario.fechaTermino >= horario.fechaInicio &&
      horario.dias.length > 0 && !!horario.horaDesde && !!horario.horaHasta && horario.horaHasta > horario.horaDesde;
  }

  protected puedeEnviar(): boolean {
    return this.centroValido() && this.supervisorValido() && !!this.formulario.tareas.trim() && this.horarioValido();
  }

  protected guardarBorrador(): void {
    this.service.guardarBorrador(this.formulario).subscribe({
      next: () => {
        this.avisoExitoso.set(true);
        this.aviso.set('Borrador guardado en tu cuenta. Puedes retomarlo al volver a esta pantalla.');
        this.intentoEnviar.set(false);
      },
      error: (error: unknown) => {
        this.avisoExitoso.set(false);
        this.aviso.set(this.service.mensajeError(error));
      },
    });
  }

  protected enviarSolicitud(): void {
    this.intentoEnviar.set(true);
    if (!this.puedeEnviar()) {
      this.avisoExitoso.set(false);
      this.aviso.set('Revisa los campos indicados antes de enviar la solicitud.');
      return;
    }
    this.service.enviar(this.formulario).subscribe({
      next: () => {
        this.avisoExitoso.set(true);
        this.aviso.set('Solicitud enviada a la Coordinadora para su revisión. Te avisaremos por correo cuando haya una decisión.');
      },
      error: (error: unknown) => {
        this.avisoExitoso.set(false);
        this.aviso.set(this.service.mensajeError(error));
      },
    });
  }

  protected mostrarError(condicionValida: boolean): boolean {
    return this.intentoEnviar() && !condicionValida;
  }

  protected correoValido(correo: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.trim());
  }

  private aplicarSupervisor(supervisor: SupervisorCentro): void {
    this.formulario.supervisorNombre = supervisor.nombre;
    this.formulario.supervisorCorreo = supervisor.correo;
    this.formulario.supervisorCargo = supervisor.cargo;
    this.formulario.supervisorUnidad = supervisor.unidad;
  }

  private limpiarSupervisor(): void {
    this.formulario.supervisorNombre = '';
    this.formulario.supervisorCorreo = '';
    this.formulario.supervisorCargo = '';
    this.formulario.supervisorTelefono = '';
    this.formulario.supervisorUnidad = '';
  }

  private formularioVacio(): SolicitudFormulario {
    return {
      modoCentro: 'Registrado', centroId: null, centroRazonSocial: '', centroRut: '',
      modoSupervisor: 'Registrado', supervisorId: null, supervisorNombre: '', supervisorCorreo: '',
      supervisorCargo: '', supervisorTelefono: '', supervisorUnidad: '', tareas: '',
      horario: { modalidad: '', fechaInicio: '', fechaTermino: '', dias: [], horaDesde: '', horaHasta: '' },
    };
  }

  private cargarFormularioDesdeBackend(): void {
    const actual = this.solicitud();
    if (actual) {
      const { id: _id, estudianteNombre: _nombre, estudianteCorreo: _correo, estado: _estado, habilitacionCumplida: _habilitacion, motivoDevolucion: _motivo, actualizadaEn: _fecha, ...formulario } = actual;
      this.formulario = structuredClone(formulario);
      this.formulario.modoCentro = actual.modoCentro;
      this.formulario.modoSupervisor = actual.modoSupervisor;
      if (actual.estado === 'Devuelta') this.reconciliarPropuestaDevuelta(actual);
      this.mostrarFormularioCentroPropuesto.set(this.formulario.modoCentro === 'Propuesto');
      return;
    }

    const borrador = this.service.borradorActual();
    this.formulario = borrador ? structuredClone(borrador) : this.formularioVacio();
    if (borrador) {
      this.centroRegistradoAnterior = borrador.centroId;
      this.mostrarFormularioCentroPropuesto.set(borrador.modoCentro === 'Propuesto');
      return;
    }
    const centro = this.centros().find((item) => item.id === this.formulario.centroId) ?? this.centros()[0];
    if (centro && this.formulario.centroId === null) {
      this.formulario.centroId = centro.id;
      this.formulario.centroRazonSocial = centro.razonSocial;
      this.formulario.centroRut = centro.rut;
      this.formulario.modoCentro = 'Registrado';
      const supervisor = centro.supervisores.find((item) => item.estado === 'Activo');
      if (supervisor) {
        this.formulario.supervisorId = supervisor.correo;
        this.formulario.modoSupervisor = 'Registrado';
        this.aplicarSupervisor(supervisor);
      }
      return;
    }

    if (!centro) this.proponerCentro();
  }

  private reconciliarPropuestaDevuelta(actual: SolicitudPractica): void {
    const centro = this.centros().find((item) => item.id === actual.centroId && item.estado === 'Activo');
    if (!centro) return;

    // Si HU-12 formalizó el centro durante la revisión, permite seleccionarlo como registrado al reenviar.
    this.formulario.modoCentro = 'Registrado';
    this.formulario.centroId = centro.id;
    this.formulario.centroRazonSocial = centro.razonSocial;
    this.formulario.centroRut = centro.rut;

    const supervisor = centro.supervisores.find((item) =>
      item.correo.toLowerCase() === actual.supervisorCorreo.toLowerCase() && item.estado === 'Activo');
    if (supervisor) {
      this.formulario.modoSupervisor = 'Registrado';
      this.formulario.supervisorId = supervisor.correo;
      this.aplicarSupervisor(supervisor);
    } else if (actual.modoSupervisor === 'Registrado') {
      // No reactiva un supervisor dado de baja ni lo deja seleccionado en el reenvío.
      this.formulario.modoSupervisor = 'Propuesto';
      this.formulario.supervisorId = null;
      this.limpiarSupervisor();
    }
  }
}
