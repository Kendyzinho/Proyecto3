import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { ActivatedRoute } from '@angular/router';

import { ConfirmacionSemanalApi, Firma, SemanaPorConfirmar, Validacion } from './confirmacion-semanal-api';

type Estado = 'cargando' | 'formulario' | 'enviada' | 'enlace_invalido' | 'error';

const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

// HU-26: el supervisor confirma o rechaza cada día registrado, sin crear una cuenta
@Component({
  selector: 'app-confirmacion-semanal',
  imports: [DatePipe, MatButtonModule, MatButtonToggleModule, MatFormFieldModule, MatIconModule, MatInputModule],
  templateUrl: './confirmacion-semanal.html',
  styleUrl: './confirmacion-semanal.scss',
})
export class ConfirmacionSemanal {
  private readonly api = inject(ConfirmacionSemanalApi);
  private readonly ruta = inject(ActivatedRoute).snapshot;
  private readonly practica = this.ruta.paramMap.get('practica') ?? '';
  private readonly semanaId = this.ruta.paramMap.get('semana') ?? '';
  private readonly firma: Firma = {
    expires: this.ruta.queryParamMap.get('expires') ?? '',
    supervisor: this.ruta.queryParamMap.get('supervisor') ?? '',
    signature: this.ruta.queryParamMap.get('signature') ?? '',
  };

  protected readonly estado = signal<Estado>('cargando');
  protected readonly semana = signal<SemanaPorConfirmar | null>(null);
  protected readonly decisiones = signal<Record<number, Validacion>>({});
  protected readonly observacion = signal('');
  protected readonly enviando = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly completa = computed(() => {
    const semana = this.semana();
    return !!semana && semana.dias.every((dia) => !!this.decisiones()[dia.id]);
  });

  constructor() {
    if (!this.firma.signature) {
      this.estado.set('enlace_invalido');
      return;
    }
    this.cargar();
  }

  protected decidir(id: number, validacion: Validacion): void {
    this.decisiones.update((actuales) => ({ ...actuales, [id]: validacion }));
  }

  protected enviar(): void {
    const semana = this.semana();
    if (!semana || !this.completa()) return;
    this.error.set(null);
    this.enviando.set(true);

    this.api.confirmar(this.practica, this.semanaId, this.firma, {
      dias: semana.dias.map((dia) => ({ id: dia.id, validacion: this.decisiones()[dia.id] })),
      observacion: this.observacion().trim() || null,
    }).subscribe({
      next: (respuesta) => this.mostrar(respuesta),
      error: (e: HttpErrorResponse) => {
        this.enviando.set(false);
        // 409: alguien ya la envió (otra pestaña); se muestra lo que quedó guardado
        if (e.status === 409) this.cargar();
        else this.fallo(e);
      },
    });
  }

  protected dia(fecha: string): string {
    const [anio, mes, dia] = fecha.split('-').map(Number);
    return `${DIAS[new Date(anio, mes - 1, dia).getDay()]} ${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}`;
  }

  protected fecha(fecha: string): string {
    return fecha.split('-').reverse().join('/');
  }

  private cargar(): void {
    this.api.ver(this.practica, this.semanaId, this.firma).subscribe({
      next: (semana) => this.mostrar(semana),
      error: (e: HttpErrorResponse) => this.fallo(e),
    });
  }

  private mostrar(semana: SemanaPorConfirmar): void {
    this.semana.set(semana);
    this.estado.set(semana.confirmada_en ? 'enviada' : 'formulario');
  }

  private fallo(e: HttpErrorResponse): void {
    if (e.status === 403 || e.status === 404) {
      this.estado.set('enlace_invalido');
      return;
    }
    const mensaje = e.status === 422
      ? 'Confirma o rechaza cada día antes de enviar.'
      : e.status === 0 ? 'No se pudo conectar con el servidor.' : 'Ocurrió un error inesperado. Inténtalo de nuevo.';
    this.error.set(mensaje);
    if (this.estado() === 'cargando') this.estado.set('error');
  }
}
