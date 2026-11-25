import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';

import { BitacoraApi } from './bitacora-api';
import { GuardarJornada, InformarInasistencia, PracticaBitacora, RegistroDiario, TipoRegistroDiario } from './bitacora.model';

@Component({
  selector: 'app-bitacora',
  imports: [FormsModule, MatButtonModule, MatFormFieldModule, MatIconModule, MatInputModule, MatProgressBarModule, MatSelectModule],
  templateUrl: './bitacora.html',
  styleUrl: './bitacora.scss',
})
export class Bitacora {
  private readonly api = inject(BitacoraApi);
  readonly practicas = signal<PracticaBitacora[]>([]);
  readonly practica = signal<(PracticaBitacora & { registros: RegistroDiario[] }) | null>(null);
  readonly cargando = signal(true);
  readonly guardando = signal(false);
  readonly error = signal('');
  readonly mensaje = signal('');
  readonly modo = signal<TipoRegistroDiario>('jornada');
  readonly registroEditado = signal<number | null>(null);

  practicaId: number | null = null;
  fecha = fechaLocal();
  horaEntrada = '';
  horaSalida = '';
  actividades = '';
  justificacion = '';
  recuperacionProgramada = '';

  constructor() {
    this.api.listar().subscribe({
      next: ({ data }) => {
        this.practicas.set(data);
        this.cargando.set(false);
        if (data.length) this.seleccionarPractica(data[0].id);
      },
      error: () => {
        this.cargando.set(false);
        this.error.set('No se pudo cargar tu bitácora. Recarga la página para intentarlo nuevamente.');
      },
    });
  }

  seleccionarPractica(id: number): void {
    this.practicaId = id;
    this.limpiarAvisos();
    this.api.detalle(id).subscribe({
      next: ({ data }) => this.practica.set(data),
      error: () => this.error.set('No se pudo cargar el detalle de la bitácora.'),
    });
    this.limpiarFormulario();
  }

  cambiarModo(modo: TipoRegistroDiario): void {
    this.modo.set(modo);
    this.limpiarFormulario();
  }

  editar(registro: RegistroDiario): void {
    if (!registro.editable) return;
    this.registroEditado.set(registro.id);
    this.fecha = registro.fecha;
    if (registro.es_inasistencia) {
      this.modo.set('inasistencia');
      this.justificacion = registro.justificacion ?? '';
      this.recuperacionProgramada = registro.recuperacion_programada ?? '';
    } else {
      this.modo.set('jornada');
      this.horaEntrada = registro.hora_entrada ?? '';
      this.horaSalida = registro.hora_salida ?? '';
      this.actividades = registro.actividades ?? '';
    }
    this.limpiarAvisos();
    document.querySelector('#formulario-bitacora')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  cancelarEdicion(): void {
    this.registroEditado.set(null);
    this.limpiarFormulario();
    this.limpiarAvisos();
  }

  guardar(): void {
    const practica = this.practica();
    if (!practica || this.practicaId === null || this.guardando()) return;

    const datos: GuardarJornada | InformarInasistencia = this.modo() === 'jornada'
      ? { tipo: 'jornada', hora_entrada: this.horaEntrada, hora_salida: this.horaSalida, actividades: this.actividades.trim() }
      : { tipo: 'inasistencia', justificacion: this.justificacion.trim(), recuperacion_programada: this.recuperacionProgramada };

    this.guardando.set(true);
    this.limpiarAvisos();
    this.api.guardar(this.practicaId, this.fecha, datos).subscribe({
      next: () => {
        this.guardando.set(false);
        this.registroEditado.set(null);
        this.mensaje.set(this.modo() === 'jornada'
          ? 'Jornada guardada. Sus horas se sumarán cuando el supervisor la confirme.'
          : 'Inasistencia informada. Se solicitó notificar por correo al supervisor y a Coordinación.');
        this.limpiarFormulario();
        this.api.detalle(this.practicaId!).subscribe({
          next: ({ data }) => this.practica.set(data),
          error: () => this.error.set('El registro se guardó, pero no se pudo actualizar la bitácora en pantalla.'),
        });
      },
      error: (e: unknown) => {
        this.guardando.set(false);
        this.error.set(this.mensajeError(e));
      },
    });
  }

  etiquetaValidacion(validacion: string): string {
    return validacion === 'confirmado' ? 'Confirmado' : validacion === 'rechazado' ? 'Rechazado' : 'Pendiente de confirmación semanal';
  }

  claseValidacion(validacion: string): string {
    return validacion === 'confirmado' ? 'estado--exito' : validacion === 'rechazado' ? 'estado--error' : 'estado--advertencia';
  }

  fechaMaxima(): string {
    return fechaLocal();
  }

  abrirSelector(input: HTMLInputElement): void {
    if (typeof input.showPicker === 'function') {
      input.showPicker();
      return;
    }

    input.focus();
    input.click();
  }

  fechaLegible(fecha: string): string {
    const [anio, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${anio}`;
  }

  fechaMinRecuperacion(): string {
    return sumarDias(this.fecha, 1);
  }

  private mensajeError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      const mensajes = Object.values(error.error?.errors ?? {}).flat() as string[];
      if (mensajes.length) return mensajes[0];
      if (error.status === 404) return 'No encontramos una práctica disponible para registrar la bitácora.';
      if (error.status === 422) return error.error?.message ?? 'Revisa los datos de la jornada e inténtalo nuevamente.';
    }
    return 'No se pudo guardar el registro. Inténtalo nuevamente.';
  }

  private limpiarFormulario(): void {
    this.fecha = fechaLocal();
    this.horaEntrada = '';
    this.horaSalida = '';
    this.actividades = '';
    this.justificacion = '';
    this.recuperacionProgramada = '';
  }

  private limpiarAvisos(): void {
    this.error.set('');
    this.mensaje.set('');
  }
}

function fechaLocal(): string {
  const hoy = new Date();
  const mes = String(hoy.getMonth() + 1).padStart(2, '0');
  const dia = String(hoy.getDate()).padStart(2, '0');
  return `${hoy.getFullYear()}-${mes}-${dia}`;
}

function sumarDias(fecha: string, cantidad: number): string {
  const [anio, mes, dia] = fecha.split('-').map(Number);
  const siguiente = new Date(anio, mes - 1, dia + cantidad);
  return `${siguiente.getFullYear()}-${String(siguiente.getMonth() + 1).padStart(2, '0')}-${String(siguiente.getDate()).padStart(2, '0')}`;
}
